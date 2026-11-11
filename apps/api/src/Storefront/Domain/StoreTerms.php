<?php

declare(strict_types=1);

namespace DoleLeilao\Storefront\Domain;

/**
 * Termos de uso da loja: as regras que o leiloeiro define (lances, pagamento, envio, falta de pagamento,
 * banimento). O participante aceita no cadastro. Texto simples: "## " abre um título e "- " um item de lista.
 * Campos entre chaves ({loja}, {whatsapp}, {email}) são trocados pelos dados da loja ao exibir.
 */
final readonly class StoreTerms
{
    public const int MAX = 20_000;

    /** @var array<string, string> campo => descrição para o painel */
    public const array FIELDS = [
        'loja' => 'Nome da loja',
        'whatsapp' => 'WhatsApp da loja',
        'email' => 'E-mail da loja',
    ];

    private const string DEFAULT = <<<'TXT'
        Estes termos valem para todos os leilões de {loja}. Ao se cadastrar e dar lances, você concorda com as regras abaixo.

        ## Cadastro
        - Para dar lances, o cadastro precisa estar completo: nome, CPF, celular e e-mail confirmado.
        - Cada pessoa pode ter um único cadastro nesta loja. Os dados precisam ser verdadeiros.
        - Seu apelido aparece para os outros participantes no auditório e no chat. Seu nome completo, CPF e contato ficam só com a loja.

        ## Lances
        - Todo lance é um compromisso de compra. Lance dado não pode ser cancelado por quem deu.
        - O valor mínimo de cada lance aparece na tela, de acordo com o incremento do item.
        - O lance automático cobre outros lances por você até o valor máximo que você definir.
        - Um lance nos últimos segundos prorroga o tempo do item, para que todos possam responder.
        - A loja pode anular um lance em caso de erro evidente ou suspeita de fraude. Quando isso acontece, o lance anterior volta a ser o vencedor.

        ## Arrematação
        - Ganha o item quem tiver o maior lance quando o tempo acabar.
        - Depois do leilão, a loja entra em contato pelo e-mail ou pelo WhatsApp do seu cadastro para combinar o pagamento e a entrega.

        ## Pagamento
        - O pagamento deve ser feito em até 3 dias úteis depois do contato da loja, pelas formas que a loja informar.
        - O item só é liberado depois que o pagamento for confirmado.

        ## Retirada e envio
        - Você pode retirar o item pessoalmente ou receber pelo correio ou por transportadora, conforme combinar com a loja.
        - O frete e o seguro do envio são pagos por quem arrematou, salvo se a loja informar outra condição no leilão.
        - Confira o item ao receber. Problemas devem ser comunicados à loja em até 7 dias.

        ## Se o pagamento não for feito
        - Sem pagamento no prazo, a arrematação pode ser cancelada e o item pode voltar a leilão ou ser oferecido ao segundo maior lance.
        - A loja pode cobrar uma multa de 20% do valor arrematado para cobrir os custos do leilão.
        - Quem desiste de uma arrematação pode ficar impedido de dar lances nesta loja.

        ## Banimento
        A loja pode suspender ou banir um participante que:
        - deixar de pagar uma arrematação;
        - der lances sem intenção de comprar ou combinados com outros participantes para alterar o preço;
        - usar o chat para ofender, divulgar outros serviços ou atrapalhar o leilão;
        - informar dados falsos no cadastro.
        Quem for banido perde o acesso aos leilões desta loja e os lances automáticos são desligados.

        ## Contato
        Dúvidas sobre estes termos: WhatsApp {whatsapp} ou e-mail {email}.
        TXT;

    private function __construct(public string $text) {}

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public static function from(string $text): self
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            throw InvalidStoreTerms::because('Escreva os termos da loja.');
        }
        if (mb_strlen($text) > self::MAX) {
            throw InvalidStoreTerms::because('Os termos podem ter até 20.000 caracteres.');
        }

        return new self($text);
    }

    /** @param array<string, string> $values */
    public function render(array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z]+)\}/',
            static fn (array $m): string => array_key_exists($m[1], self::FIELDS) ? ($values[$m[1]] ?? '') : $m[0],
            $this->text,
        );
    }
}
