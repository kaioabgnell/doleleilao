<?php

declare(strict_types=1);

namespace DoleLeilao\Auction\Domain;

use DoleLeilao\Auction\Domain\Event\AuctionFinished;
use DoleLeilao\Auction\Domain\Event\AuctionScheduled;
use DoleLeilao\Auction\Domain\Event\AuctionStarted;
use DoleLeilao\Shared\Domain\RuleViolations;

/**
 * Lote de leilão: o grupo de itens do dia (D-22). Os itens são agregados próprios (AuctionItem);
 * aqui ficam os dados do lote, o status (RF-D06) e as regras de agendamento.
 */
final class Auction
{
    public const int NAME_MAX = 80;

    public const int DESCRIPTION_MAX = 5000;

    public const int MAX_ITEMS = 200;

    /** @var list<object> */
    private array $events = [];

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $auctioneerId,
        private string $name,
        private string $slug,
        private string $description,
        private \DateTimeImmutable $startsAt,
        private AuctionStatus $status,
        private AuctionSettings $settings,
        private ?\DateTimeImmutable $startedAt = null,
        private ?\DateTimeImmutable $finishedAt = null,
        private bool $visibleInStore = true,
    ) {}

    public static function draft(
        int $id,
        string $publicId,
        int $auctioneerId,
        string $name,
        string $slug,
        string $description,
        \DateTimeImmutable $startsAt,
        AuctionSettings $settings,
        \DateTimeImmutable $now,
    ): self {
        [$name, $description] = self::validate($name, $description, $startsAt, $now);

        return new self($id, $publicId, $auctioneerId, $name, $slug, $description, $startsAt, AuctionStatus::Draft, $settings);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function status(): AuctionStatus
    {
        return $this->status;
    }

    public function settings(): AuctionSettings
    {
        return $this->settings;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** Encerrado: o leiloeiro escolhe se o lote continua no histórico da loja. */
    public function isVisibleInStore(): bool
    {
        return $this->visibleInStore;
    }

    public function changeStoreVisibility(bool $visible): void
    {
        if ($this->status !== AuctionStatus::Finished) {
            throw new InvalidAuction('status', 'Só um lote encerrado pode ser escondido da loja. Antes disso, volte o lote para rascunho.');
        }
        $this->visibleInStore = $visible;
    }

    /** Agendado e ao vivo têm gente acompanhando: precisam ser cancelados antes. */
    public function assertDeletable(): void
    {
        if ($this->status === AuctionStatus::Scheduled || $this->status === AuctionStatus::Live) {
            throw new InvalidAuction('status', 'Só dá para apagar rascunhos, lotes cancelados e encerrados. Cancele o lote antes.');
        }
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function update(string $name, string $description, \DateTimeImmutable $startsAt, AuctionSettings $settings, \DateTimeImmutable $now): void
    {
        $this->assertEditable();
        // Data de início no passado só é aceita se ela não mudou (ex.: editar a descrição de um lote atrasado).
        [$this->name, $this->description] = self::validate($name, $description, $startsAt, $startsAt == $this->startsAt ? $startsAt->modify('-1 second') : $now);
        $this->startsAt = $startsAt;
        $this->settings = $settings;
    }

    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw new InvalidAuction('status', $this->status === AuctionStatus::Cancelled
                ? 'Este lote foi cancelado.'
                : 'Este lote já começou. Use os controles do pregão.');
        }
    }

    /**
     * RF-D01: precisa de pelo menos 1 item e da data no futuro. D-54: e de um cartão de crédito
     * cadastrado, onde a taxa dos itens arrematados é debitada.
     */
    public function schedule(int $itemCount, bool $hasPaymentCard, \DateTimeImmutable $now): void
    {
        if ($this->status !== AuctionStatus::Draft) {
            throw new InvalidAuction('status', 'Só um rascunho pode ser agendado.');
        }
        if ($itemCount < 1) {
            throw new InvalidAuction('items', 'Adicione pelo menos 1 item antes de agendar.');
        }
        if ($this->startsAt <= $now) {
            throw new InvalidAuction('starts_at', 'A data de início precisa estar no futuro.');
        }
        if (! $hasPaymentCard) {
            throw new InvalidAuction('payment_card', 'Cadastre um cartão de crédito em Configurações → Pagamento para agendar o leilão.');
        }
        $this->status = AuctionStatus::Scheduled;
        $this->events[] = new AuctionScheduled($this->id, $this->publicId, $this->auctioneerId, $now);
    }

    /** Tira da loja para editar com calma (volta a rascunho). */
    public function unschedule(): void
    {
        if ($this->status !== AuctionStatus::Scheduled) {
            throw new InvalidAuction('status', 'Só um lote agendado volta para rascunho.');
        }
        $this->status = AuctionStatus::Draft;
    }

    public function cancel(): void
    {
        if (! $this->status->isEditable()) {
            throw new InvalidAuction('status', 'Este lote não pode mais ser cancelado.');
        }
        $this->status = AuctionStatus::Cancelled;
    }

    public function isDue(\DateTimeImmutable $now): bool
    {
        return $this->status === AuctionStatus::Scheduled && $this->startsAt <= $now;
    }

    /** O relógio inicia na hora marcada. O leiloeiro também pode iniciar antes (RF-D05). */
    public function start(\DateTimeImmutable $now): void
    {
        if ($this->status !== AuctionStatus::Scheduled) {
            throw new InvalidAuction('status', 'Só um lote agendado pode ser iniciado.');
        }
        $this->status = AuctionStatus::Live;
        $this->startedAt = $now;
        $this->events[] = new AuctionStarted($this->id, $this->publicId, $this->auctioneerId, $now);
    }

    public function finish(\DateTimeImmutable $now): void
    {
        if ($this->status !== AuctionStatus::Live) {
            throw new InvalidAuction('status', 'Só um lote ao vivo pode ser encerrado.');
        }
        $this->status = AuctionStatus::Finished;
        $this->finishedAt = $now;
        $this->events[] = new AuctionFinished($this->id, $this->publicId, $this->auctioneerId, $now);
    }

    /** @return array{string, string} nome e descrição normalizados */
    private static function validate(string $name, string $description, \DateTimeImmutable $startsAt, \DateTimeImmutable $now): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $description = trim($description);
        $errors = [];
        if (mb_strlen($name) < 3 || mb_strlen($name) > self::NAME_MAX) {
            $errors[] = new InvalidAuction('name', 'O nome precisa ter de 3 a '.self::NAME_MAX.' caracteres.');
        }
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors[] = new InvalidAuction('description', 'A descrição pode ter até '.self::DESCRIPTION_MAX.' caracteres.');
        }
        if ($startsAt <= $now) {
            $errors[] = new InvalidAuction('starts_at', 'A data de início precisa estar no futuro.');
        }
        if (count($errors) === 1) {
            throw $errors[0];
        }
        if ($errors !== []) {
            throw new RuleViolations($errors);
        }

        return [$name, $description];
    }
}
