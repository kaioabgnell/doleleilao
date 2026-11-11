<?php

declare(strict_types=1);

namespace DoleLeilao\Identity\Infrastructure;

use DoleLeilao\Identity\Infrastructure\Notifications\ResetAuctioneerPassword;
use DoleLeilao\Identity\Infrastructure\Notifications\VerifyAuctioneerEmail;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Storefront\Infrastructure\Eloquent\AuctioneerRecord;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Conta de acesso ao painel do leiloeiro (tabela `auctioneer_users`). Na v1 só existe o dono;
 * na v2 entram os membros da equipe.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property Carbon $terms_accepted_at
 * @property-read AuctioneerRecord $auctioneer
 */
final class AuctioneerUser extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    use CanResetPasswordTrait;
    use HasApiTokens;
    use HasPublicId;
    use MicrosecondTimestamps;
    use MustVerifyEmailTrait;
    use Notifiable;

    /** Escopo do token do painel (specs/04-arquitetura.md#autenticação). */
    public const string ABILITY = 'auctioneer';

    protected $table = 'auctioneer_users';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<AuctioneerRecord, $this> */
    public function auctioneer(): BelongsTo
    {
        return $this->belongsTo(AuctioneerRecord::class, 'auctioneer_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyAuctioneerEmail);
    }

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetAuctioneerPassword($token));
    }
}
