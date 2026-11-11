<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain\Onboarding;

/**
 * Seção "Comece seu primeiro leilão" do dashboard (D-42, RF-A10 a RF-A16).
 * Leitura calculada a partir dos dados reais. Depois de concluída, não volta atrás.
 */
final readonly class OnboardingProgress
{
    /**
     * @param  array<string, bool>  $done
     * @param  list<OnboardingStep>  $auctionNeeds  passos que faltam para dar para agendar
     */
    private function __construct(
        private OnboardingFacts $facts,
        private array $done,
        private array $auctionNeeds,
        private bool $completed,
        private bool $justCompleted,
        private bool $dismissed,
    ) {}

    public static function evaluate(
        OnboardingFacts $facts,
        ?\DateTimeImmutable $completedAt,
        ?\DateTimeImmutable $dismissedAt,
    ): self {
        $done = [
            OnboardingStep::Company->value => $facts->emailVerified && $facts->hasContactPhone && $facts->templateChosen,
            OnboardingStep::Products->value => $facts->availableProducts > 0,
            OnboardingStep::Payment->value => $facts->hasPaymentCard,
            OnboardingStep::Auction->value => $facts->scheduledAuctions > 0,
        ];
        // Agendar exige produto disponível (RF-A13) e o cartão (D-54).
        $auctionNeeds = $completedAt !== null ? [] : array_values(array_filter([
            $facts->availableProducts > 0 ? null : OnboardingStep::Products,
            $facts->hasPaymentCard ? null : OnboardingStep::Payment,
        ]));
        $alreadyCompleted = $completedAt !== null;
        $allDone = ! in_array(false, $done, true);

        // RF-A16: depois de concluída, os passos não voltam atrás. Exceção (D-55): o cartão é
        // obrigatório para agendar, então o pagamento vale pelo dado real. Quem concluiu antes de o
        // passo existir volta a ver a seção até cadastrar o cartão.
        if ($alreadyCompleted) {
            foreach (array_keys($done) as $key) {
                $done[$key] = $key === OnboardingStep::Payment->value ? $facts->hasPaymentCard : true;
            }
        }

        return new self(
            facts: $facts,
            done: $done,
            auctionNeeds: $auctionNeeds,
            completed: ! in_array(false, $done, true),
            justCompleted: ! $alreadyCompleted && $allDone,
            dismissed: $dismissedAt !== null,
        );
    }

    public function isDone(OnboardingStep $step): bool
    {
        return $this->done[$step->value];
    }

    public function isBlocked(OnboardingStep $step): bool
    {
        return $this->blockedBy($step) !== [];
    }

    /**
     * O que falta antes de dar para fazer o passo: o leilão pede um produto disponível (RF-A13) e
     * o cartão de crédito (D-54). Os outros passos não dependem de nada.
     *
     * @return list<OnboardingStep>
     */
    public function blockedBy(OnboardingStep $step): array
    {
        return $step === OnboardingStep::Auction && ! $this->isDone($step) ? $this->auctionNeeds : [];
    }

    /**
     * O que falta para concluir o passo 1 (RF-A11), para o painel mostrar cada pendência.
     *
     * @return list<'email'|'contact_phone'|'template'>
     */
    public function missingForCompany(): array
    {
        if ($this->isDone(OnboardingStep::Company)) {
            return [];
        }

        return array_values(array_filter([
            $this->facts->emailVerified ? null : 'email',
            $this->facts->hasContactPhone ? null : 'contact_phone',
            $this->facts->templateChosen ? null : 'template',
        ]));
    }

    public function doneCount(): int
    {
        return count(array_filter($this->done));
    }

    /** O próximo passo pendente, na ordem sugerida (RF-A14). */
    public function next(): ?OnboardingStep
    {
        foreach (OnboardingStep::cases() as $step) {
            if (! $this->isDone($step)) {
                return $step;
            }
        }

        return null;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /** Concluída agora: quem chamou grava a data e publica `PrimeirosPassosConcluidos`. */
    public function justCompleted(): bool
    {
        return $this->justCompleted;
    }

    /** Ocultada só fica escondida enquanto estiver concluída: um passo obrigatório pendente a traz de volta. */
    public function isVisible(): bool
    {
        return ! $this->dismissed || ! $this->completed;
    }

    public function assertCanDismiss(): void
    {
        if (! $this->completed) {
            throw new OnboardingNotCompleted;
        }
    }
}
