<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

use DoleLeilao\Identity\Infrastructure\Notifications\ResetBidderPassword;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyBidderEmail;
use DoleLeilao\Shared\Domain\Address;
use DoleLeilao\Shared\Domain\TaxDocument;
use DoleLeilao\Shared\Infrastructure\Eloquent\AddressColumns;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Participante de uma loja (D-11): a conta vale só para aquele leiloeiro. Token com o escopo
 * `bidder:{auctioneer_id}`. CPF obrigatório, criptografado e com hash por loja (D-32).
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property string $name
 * @property string $nickname
 * @property string $cpf_enc
 * @property string $cpf_hash
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property string|null $photo_path
 * @property string|null $avatar_key
 * @property int|null $alert_minutes
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $profile_completed_at
 * @property Carbon|null $last_seen_at
 * @property Carbon $consent_at
 * @property string|null $address_postal_code
 * @property string|null $address_street
 * @property string|null $address_number
 * @property string|null $address_complement
 * @property string|null $address_district
 * @property string|null $address_city
 * @property string|null $address_state
 * @property-read AuctioneerRecord $auctioneer
 */
final class BidderUser extends Authenticatable
{
    use HasApiTokens;
    use HasPublicId;
    use MicrosecondTimestamps;
    use Notifiable;

    /** Galeria fixa de avatares (D-38). Mesmas chaves de `avatarKeys` no pacote design-tokens. */
    public const array AVATAR_KEYS = [
        'avatar-01', 'avatar-02', 'avatar-03', 'avatar-04', 'avatar-05', 'avatar-06',
        'avatar-07', 'avatar-08', 'avatar-09', 'avatar-10', 'avatar-11', 'avatar-12',
    ];

    protected $table = 'bidders';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'cpf_enc', 'cpf_hash'];

    protected function casts(): array
    {
        return [
            'cpf_enc' => 'encrypted',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'alert_minutes' => 'integer',
            'last_seen_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    /** Para onde o leiloeiro envia os arremates (opcional). */
    public function shippingAddress(): ?Address
    {
        return AddressColumns::read($this);
    }

    public function changeShippingAddress(?Address $address): void
    {
        $this->forceFill(AddressColumns::write($address));
    }

    public static function abilityFor(int $auctioneerId): string
    {
        return "bidder:{$auctioneerId}";
    }

    /** @return BelongsTo<AuctioneerRecord, $this> */
    public function auctioneer(): BelongsTo
    {
        return $this->belongsTo(AuctioneerRecord::class, 'auctioneer_id');
    }

    public function cpf(): TaxDocument
    {
        return TaxDocument::cpf($this->cpf_enc);
    }

    /**
     * O que falta para o cadastro completo (D-23): nome, CPF, telefone e e-mail validado.
     * Foto ou avatar são opcionais (D-38).
     *
     * @return list<'name'|'cpf'|'phone'|'email'>
     */
    public function missingForEligibility(): array
    {
        return array_values(array_filter([
            trim($this->name) === '' ? 'name' : null,
            $this->cpf_hash === '' ? 'cpf' : null,
            $this->phone === '' ? 'phone' : null,
            $this->email_verified_at === null ? 'email' : null,
        ]));
    }

    /** `Bidder::isEligible()` do glossário: habilitado em todos os lotes da loja. */
    public function isEligible(): bool
    {
        return $this->missingForEligibility() === [];
    }

    public function markEmailVerified(): void
    {
        $this->email_verified_at ??= now();
        $becameEligible = $this->profile_completed_at === null && $this->isEligible();
        if ($becameEligible) {
            $this->profile_completed_at = now();
        }
        $this->save();
        if ($becameEligible) {
            event(new BidderBecameEligible($this->id, $this->auctioneer_id));
        }
    }

    public function sendEmailVerification(string $storeSlug): void
    {
        $this->notify(new VerifyBidderEmail($storeSlug));
    }

    public function sendPasswordReset(string $token, string $storeSlug): void
    {
        $this->notify(new ResetBidderPassword($token, $storeSlug));
    }
}
