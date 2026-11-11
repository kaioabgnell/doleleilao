<?php

declare(strict_types=1);

namespace DoleLeilao\Chat\Infrastructure\Eloquent;

use DoleLeilao\Identity\Infrastructure\AuctioneerUser;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mensagem do chat do auditório. Apagar (moderação) preenche `deleted_*` e esconde o texto.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $auction_id
 * @property int|null $bidder_id
 * @property int|null $auctioneer_user_id
 * @property string $body
 * @property int|null $reply_to_id
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property Carbon $created_at
 * @property-read BidderUser|null $bidder
 * @property-read AuctioneerUser|null $auctioneerUser
 * @property-read ChatMessageRecord|null $replyTo
 */
final class ChatMessageRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public const null UPDATED_AT = null;

    protected $table = 'chat_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deleted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BidderUser, $this> */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(BidderUser::class, 'bidder_id');
    }

    /** @return BelongsTo<AuctioneerUser, $this> */
    public function auctioneerUser(): BelongsTo
    {
        return $this->belongsTo(AuctioneerUser::class, 'auctioneer_user_id');
    }

    /** @return BelongsTo<ChatMessageRecord, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}
