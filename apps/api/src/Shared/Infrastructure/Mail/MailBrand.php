<?php

declare(strict_types=1);

namespace DoleLeilao\Shared\Infrastructure\Mail;

use DoleLeilao\Shared\Infrastructure\Media\MediaStorage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;

/**
 * Marca dos e-mails (specs/07: duas camadas de marca, D-01). E-mails da loja levam o nome, a logo e a
 * cor primária do leiloeiro, e o rodapé "desenvolvido por Doleleilao.com.br" com UTM (D-06, D-20).
 * E-mails da plataforma (para o leiloeiro) levam a marca Dole Leilão. As cores vêm do catálogo gerado
 * pelo design-tokens (contraste garantido por teste).
 */
final readonly class MailBrand
{
    private function __construct(
        public string $name,
        public ?string $logoUrl,
        public string $button,
        public string $onButton,
        public string $link,
        public string $footer,
        public ?string $footerUrl,
        public bool $isPlatform,
    ) {}

    public static function platform(): self
    {
        $email = self::emailColors()['copper'] ?? ['button' => '#8A5A38', 'onButton' => '#FFFFFF', 'link' => '#8A5A38'];

        return new self('Dole Leilão', null, $email['button'], $email['onButton'], $email['link'], 'Dole Leilão · leilões online com a sua marca', self::frontend(), true);
    }

    public static function store(int $auctioneerId): self
    {
        $store = DB::table('auctioneers')
            ->join('storefront_settings', 'storefront_settings.auctioneer_id', '=', 'auctioneers.id')
            ->where('auctioneers.id', $auctioneerId)
            ->first(['auctioneers.display_name', 'auctioneers.slug', 'storefront_settings.primary_color_key', 'storefront_settings.logo_path', 'storefront_settings.logo_kind']);

        if ($store === null) {
            return self::platform();
        }
        $colors = self::emailColors();
        $key = is_string($store->primary_color_key) ? $store->primary_color_key : 'copper';
        $email = $colors[$key] ?? $colors['copper'] ?? ['button' => '#8A5A38', 'onButton' => '#FFFFFF', 'link' => '#8A5A38'];
        // SVG não abre na maioria dos clientes de e-mail: nesse caso, o nome da loja em texto.
        $logo = $store->logo_kind === 'webp' && is_string($store->logo_path) ? app(MediaStorage::class)->url($store->logo_path) : null;
        $slug = is_string($store->slug) ? $store->slug : '';

        return new self(
            is_string($store->display_name) ? $store->display_name : 'Loja',
            $logo,
            $email['button'],
            $email['onButton'],
            $email['link'],
            'desenvolvido por Doleleilao.com.br',
            self::frontend().'/?utm_source='.rawurlencode($slug).'&utm_medium=email',
            false,
        );
    }

    /** Aplica o modelo da marca a uma mensagem (o texto continua vindo de greeting/line/action). */
    public function apply(MailMessage $message): MailMessage
    {
        return $message->view(['mail.branded', 'mail.branded-text'], ['brand' => $this]);
    }

    /** @return array<string, array{button: string, onButton: string, link: string}> */
    private static function emailColors(): array
    {
        static $colors = null;
        if ($colors === null) {
            $json = json_decode((string) file_get_contents(config()->string('doleleilao.theme_catalog')), true);
            $colors = is_array($json) && is_array($json['email'] ?? null) ? $json['email'] : [];
        }

        /** @var array<string, array{button: string, onButton: string, link: string}> */
        return $colors;
    }

    private static function frontend(): string
    {
        return rtrim(config()->string('doleleilao.frontend_url'), '/');
    }
}
