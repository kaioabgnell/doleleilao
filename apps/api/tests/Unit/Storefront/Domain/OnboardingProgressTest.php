<?php

declare(strict_types=1);

use DoleLeilao\Storefront\Domain\Onboarding\OnboardingFacts;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingNotCompleted;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingProgress;
use DoleLeilao\Storefront\Domain\Onboarding\OnboardingStep;

function facts(bool $email = false, bool $contact = false, bool $template = false, int $products = 0, int $auctions = 0, bool $card = false): OnboardingFacts
{
    return new OnboardingFacts(
        emailVerified: $email,
        hasContactPhone: $contact,
        templateChosen: $template,
        availableProducts: $products,
        hasPaymentCard: $card,
        scheduledAuctions: $auctions,
    );
}

it('leiloeiro recém-cadastrado: nada feito, próximo passo é dados da empresa', function () {
    $progress = OnboardingProgress::evaluate(facts(contact: true), null, null);

    expect($progress->doneCount())->toBe(0)
        ->and($progress->next())->toBe(OnboardingStep::Company)
        ->and($progress->isCompleted())->toBeFalse()
        ->and($progress->isVisible())->toBeTrue();
});

it('passo 1 exige e-mail validado, telefone de contato e modelo escolhido (RF-A11)', function (bool $email, bool $contact, bool $template, bool $done) {
    $progress = OnboardingProgress::evaluate(facts($email, $contact, $template), null, null);

    expect($progress->isDone(OnboardingStep::Company))->toBe($done);
})->with([
    [true, true, true, true],
    [false, true, true, false],
    [true, false, true, false],
    [true, true, false, false],
]);

it('passo 2 exige 1 produto disponível (RF-A12)', function () {
    $progress = OnboardingProgress::evaluate(facts(true, true, true, products: 1), null, null);

    expect($progress->isDone(OnboardingStep::Products))->toBeTrue()
        ->and($progress->isDone(OnboardingStep::Payment))->toBeFalse()
        ->and($progress->next())->toBe(OnboardingStep::Payment)
        ->and($progress->doneCount())->toBe(2);
});

it('passo 3 exige o cartão de crédito em Configurações → Pagamento (D-55)', function () {
    $progress = OnboardingProgress::evaluate(facts(true, true, true, products: 1, card: true), null, null);

    expect($progress->isDone(OnboardingStep::Payment))->toBeTrue()
        ->and($progress->next())->toBe(OnboardingStep::Auction)
        ->and($progress->doneCount())->toBe(3);
});

it('os passos seguem a ordem: empresa, produtos, pagamento, leilão', function () {
    expect(array_map(fn (OnboardingStep $s) => $s->value, OnboardingStep::cases()))->toBe(['company', 'products', 'payment', 'auction']);
});

it('a ordem é só sugerida: dá para concluir um passo fora de ordem (RF-A14)', function () {
    $progress = OnboardingProgress::evaluate(facts(products: 2), null, null);

    expect($progress->isDone(OnboardingStep::Products))->toBeTrue()
        ->and($progress->next())->toBe(OnboardingStep::Company);
});

it('o passo do leilão fica bloqueado sem produto disponível ou sem cartão, e diz o que falta', function () {
    $blocked = fn (OnboardingFacts $f) => OnboardingProgress::evaluate($f, null, null)->blockedBy(OnboardingStep::Auction);

    expect($blocked(facts()))->toBe([OnboardingStep::Products, OnboardingStep::Payment])
        ->and($blocked(facts(products: 1)))->toBe([OnboardingStep::Payment])
        ->and($blocked(facts(card: true)))->toBe([OnboardingStep::Products])
        ->and($blocked(facts(products: 1, card: true)))->toBe([])
        ->and(OnboardingProgress::evaluate(facts(products: 1), null, null)->isBlocked(OnboardingStep::Auction))->toBeTrue()
        ->and(OnboardingProgress::evaluate(facts(products: 1, card: true), null, null)->isBlocked(OnboardingStep::Auction))->toBeFalse()
        ->and(OnboardingProgress::evaluate(facts(), null, null)->isBlocked(OnboardingStep::Payment))->toBeFalse();
});

it('o progresso vem dos dados: sem produto, o passo 2 volta a pendente (RF-A15)', function () {
    $before = OnboardingProgress::evaluate(facts(true, true, true, products: 1), null, null);
    $after = OnboardingProgress::evaluate(facts(true, true, true, products: 0), null, null);

    expect($before->isDone(OnboardingStep::Products))->toBeTrue()
        ->and($after->isDone(OnboardingStep::Products))->toBeFalse();
});

it('sem o cartão, a seção não conclui, mesmo com lote agendado antes de o cartão ser exigido', function () {
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 1, 1), null, null);

    expect($progress->isCompleted())->toBeFalse()
        ->and($progress->next())->toBe(OnboardingStep::Payment)
        ->and($progress->doneCount())->toBe(3);
});

it('com os 4 passos feitos, a seção acaba de ser concluída', function () {
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 1, 1, card: true), null, null);

    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->justCompleted())->toBeTrue()
        ->and($progress->next())->toBeNull()
        ->and($progress->doneCount())->toBe(4);
});

it('depois de concluída, a seção não volta atrás mesmo que os dados mudem (RF-A16)', function () {
    $completedAt = new DateTimeImmutable('2026-11-01 12:00:00');
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 0, 0, card: true), $completedAt, null);

    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->justCompleted())->toBeFalse()
        ->and($progress->doneCount())->toBe(4);
});

it('o cartão é obrigatório: sem ele a seção volta, mesmo concluída e ocultada antes do passo existir (D-55)', function () {
    $at = new DateTimeImmutable('2026-11-08 17:44:00');
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 3, 1), $at, $at);

    expect($progress->isVisible())->toBeTrue()
        ->and($progress->isCompleted())->toBeFalse()
        ->and($progress->justCompleted())->toBeFalse()
        ->and($progress->isDone(OnboardingStep::Company))->toBeTrue()
        ->and($progress->isDone(OnboardingStep::Products))->toBeTrue()
        ->and($progress->isDone(OnboardingStep::Auction))->toBeTrue()
        ->and($progress->isDone(OnboardingStep::Payment))->toBeFalse()
        ->and($progress->next())->toBe(OnboardingStep::Payment)
        ->and($progress->doneCount())->toBe(3);
});

it('com o cartão cadastrado, a seção que tinha sido ocultada some de novo', function () {
    $at = new DateTimeImmutable('2026-11-08 17:44:00');
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 3, 1, card: true), $at, $at);

    expect($progress->isCompleted())->toBeTrue()
        ->and($progress->isVisible())->toBeFalse();
});

it('só pode ser ocultada depois de concluída', function () {
    OnboardingProgress::evaluate(facts(true, true, true, 1, 0), null, null)->assertCanDismiss();
})->throws(OnboardingNotCompleted::class);

it('depois de ocultada não aparece mais', function () {
    $at = new DateTimeImmutable('2026-11-01 12:00:00');
    $progress = OnboardingProgress::evaluate(facts(true, true, true, 1, 1, card: true), $at, $at);

    expect($progress->isVisible())->toBeFalse();
});

it('diz o que falta no passo 1', function () {
    expect(OnboardingProgress::evaluate(facts(contact: true), null, null)->missingForCompany())->toBe(['email', 'template'])
        ->and(OnboardingProgress::evaluate(facts(), null, null)->missingForCompany())->toBe(['email', 'contact_phone', 'template'])
        ->and(OnboardingProgress::evaluate(facts(true, true, true), null, null)->missingForCompany())->toBe([]);
});
