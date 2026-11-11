<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Infrastructure\Eloquent;

use DoleLeilao\Shared\Infrastructure\Eloquent\HasPublicId;
use DoleLeilao\Shared\Infrastructure\Eloquent\MicrosecondTimestamps;
use DoleLeilao\Shared\Infrastructure\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mensagem do leiloeiro ao vencedor (RF-G05). `channels`: email e/ou whatsapp (o WhatsApp abre no
 * aparelho do leiloeiro; aqui fica o registro). Aparece na "Minha conta" do participante.
 *
 * @property int $id
 * @property string $public_id
 * @property int $auctioneer_id
 * @property int $award_id
 * @property int|null $auctioneer_user_id
 * @property string $body
 * @property list<string> $channels
 * @property Carbon $sent_at
 */
final class WinnerMessageRecord extends Model
{
    use BelongsToTenant;
    use HasPublicId;
    use MicrosecondTimestamps;

    public $timestamps = false;

    protected $table = 'winner_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'sent_at' => 'immutable_datetime'];
    }
}
