<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Problem;
use App\Http\Resources\BidderResource;
use DoleLeilao\Identity\Infrastructure\BidderBans;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Domain\Phone;
use DoleLeilao\Shared\Domain\RuleViolation;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Domain\ViolationCollector;
use DoleLeilao\Shared\Infrastructure\TaxDocumentHasher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Cadastro, login e senha do participante, sempre dentro de uma loja (RF-E03, RF-E04, D-11, D-32). */
final class BidderAuthController
{
    use ResolvesStore;

    public function register(Request $request, string $slug, TaxDocumentHasher $hasher): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'cpf' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:255'],
            'accept_terms' => ['accepted'],
        ]);

        $errors = new ViolationCollector;
        $cpf = $errors->attempt(fn () => TaxDocument::cpf($request->string('cpf')->value(), 'cpf'));
        $phone = $errors->attempt(fn () => Phone::fromString($request->string('phone')->value()));
        $email = mb_strtolower($request->string('email')->trim()->value());
        $base = BidderUser::query()->where('auctioneer_id', $store->id);

        if ((clone $base)->where('email', $email)->exists()) {
            $errors->add($this->violation('email', 'Este e-mail já tem cadastro nesta loja. Entre com ele ou recupere a senha.'));
        }
        if ($cpf !== null && (clone $base)->where('cpf_hash', $hasher->hash($cpf))->exists()) {
            $errors->add($this->violation('cpf', 'Este CPF já tem cadastro nesta loja.'));
        }
        $errors->throwIfAny();
        assert($cpf !== null && $phone !== null);

        $name = trim((string) preg_replace('/\s+/u', ' ', $request->string('name')->value()));
        try {
            $bidder = BidderUser::query()->create([
                'auctioneer_id' => $store->id,
                'name' => $name,
                'nickname' => self::nicknameFrom($name),
                'cpf_enc' => $cpf->value,
                'cpf_hash' => $hasher->hash($cpf),
                'email' => $email,
                'phone' => $phone->value,
                'password' => $request->string('password')->value(),
                'consent_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Este e-mail ou CPF já tem cadastro nesta loja.']);
        }

        $bidder->sendEmailVerification($store->slug);

        return response()->json($this->session($bidder), 201);
    }

    public function login(Request $request, string $slug): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string', 'max:255']]);
        $password = $request->string('password')->value();
        $bidder = BidderUser::query()->where('auctioneer_id', $store->id)
            ->where('email', mb_strtolower($request->string('email')->trim()->value()))->first();

        if ($bidder === null) {
            Hash::make($password);
        }
        if ($bidder === null || ! Hash::check($password, $bidder->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }
        // RF-G02: quem foi banido não entra mais na conta desta loja.
        if (app(BidderBans::class)->isBanned($bidder)) {
            return Problem::make(403, 'Sua conta está bloqueada nesta loja. Fale com o leiloeiro.', ['reason' => 'banned']);
        }

        return response()->json($this->session($bidder));
    }

    /** Link do e-mail (assinado, 24 h): confirma e volta para "Minha conta" da loja. */
    public function verifyEmail(Request $request, string $bidder, string $hash): RedirectResponse
    {
        $record = BidderUser::query()->with('auctioneer')->where('public_id', $bidder)->first();
        $front = rtrim(config()->string('doleleilao.frontend_url'), '/');
        $account = $record === null ? $front : "{$front}/{$record->auctioneer->slug}/minha-conta?email=";

        if ($record === null || ! hash_equals(sha1($record->email), $hash)) {
            return redirect()->away($record === null ? $front : $account.'link-invalido');
        }
        if (! $request->hasValidSignature()) {
            return redirect()->away($account.'link-expirado');
        }
        $record->markEmailVerified();

        return redirect()->away($account.'verificado');
    }

    public function forgotPassword(Request $request, string $slug): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate(['email' => ['required', 'string', 'email']]);
        $bidder = BidderUser::query()->where('auctioneer_id', $store->id)
            ->where('email', mb_strtolower($request->string('email')->trim()->value()))->first();

        if ($bidder !== null) {
            $token = Str::random(64);
            DB::table('bidder_password_resets')->updateOrInsert(
                ['bidder_id' => $bidder->id],
                ['token_hash' => hash('sha256', $token), 'created_at' => now()],
            );
            $bidder->sendPasswordReset($token, $store->slug);
        }

        return response()->json(['message' => 'Se este e-mail estiver cadastrado nesta loja, você vai receber um link para criar uma nova senha.'], 202);
    }

    public function resetPassword(Request $request, string $slug): JsonResponse
    {
        $store = $this->resolveStore($slug);
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:255'],
        ]);
        $bidder = BidderUser::query()->where('auctioneer_id', $store->id)
            ->where('email', mb_strtolower($request->string('email')->trim()->value()))->first();
        $row = $bidder === null ? null : DB::table('bidder_password_resets')->where('bidder_id', $bidder->id)->first();

        $valid = $bidder !== null && $row !== null
            && is_string($row->token_hash ?? null) && is_string($row->created_at ?? null)
            && hash_equals($row->token_hash, hash('sha256', $request->string('token')->value()))
            && now()->subMinutes(60)->lt($row->created_at);
        if (! $valid) {
            throw ValidationException::withMessages(['token' => 'Este link é inválido ou expirou. Peça um novo.']);
        }

        DB::transaction(function () use ($bidder, $request): void {
            $bidder->forceFill(['password' => $request->string('password')->value()])->save();
            $bidder->tokens()->delete();
            DB::table('bidder_password_resets')->where('bidder_id', $bidder->id)->delete();
            $bidder->markEmailVerified();
        });

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
    }

    /** "Maria Silva Souza" → "Maria S." (apelido que aparece no auditório; editável). */
    public static function nicknameFrom(string $name): string
    {
        $words = array_values(array_filter(explode(' ', $name)));
        $nick = $words[0] ?? 'Participante';
        if (count($words) > 1) {
            $nick .= ' '.mb_strtoupper(mb_substr((string) end($words), 0, 1)).'.';
        }

        return mb_substr($nick, 0, 30);
    }

    /** @return array<string, mixed> */
    private function session(BidderUser $bidder): array
    {
        $expiresAt = now()->addMinutes(config()->integer('sanctum.expiration'));
        $token = $bidder->createToken('web', [BidderUser::abilityFor($bidder->auctioneer_id)], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            'bidder' => new BidderResource($bidder->refresh()),
        ];
    }

    private function violation(string $field, string $message): RuleViolation
    {
        return new class($field, $message) extends RuleViolation {};
    }
}
