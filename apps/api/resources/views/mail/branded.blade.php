{{-- Modelo HTML dos e-mails (MailBrand). Tabelas e estilos inline: é o que os clientes de e-mail entendem. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $subject ?? $brand->name }}</title>
</head>
<body style="margin:0;padding:0;background:#F6F5F2;font-family:Inter,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#2F3B3E;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F5F2;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
  <tr><td style="padding:8px 4px 20px;">
    @if ($brand->logoUrl)
      <img src="{{ $brand->logoUrl }}" alt="{{ $brand->name }}" height="40" style="display:block;height:40px;width:auto;border:0;">
    @elseif ($brand->isPlatform)
      <span style="font-family:Montserrat,Arial,sans-serif;font-size:22px;font-weight:800;letter-spacing:.02em;color:#2F3B3E;">DOLE <span style="color:{{ $brand->link }};font-weight:600;font-size:13px;letter-spacing:.3em;">LEILÃO</span></span>
    @else
      <span style="font-size:22px;font-weight:700;color:#2F3B3E;">{{ $brand->name }}</span>
    @endif
  </td></tr>
  <tr><td style="background:#FFFFFF;border:1px solid #E3E1DC;border-radius:12px;padding:28px 24px;">
    @if (! empty($greeting))
      <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#2F3B3E;">{{ $greeting }}</h1>
    @endif
    @foreach ($introLines as $line)
      {{-- HtmlString já vem escapado (com <br>); texto comum é escapado aqui. --}}
      <p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:#2F3B3E;">{!! $line instanceof \Illuminate\Contracts\Support\Htmlable ? $line->toHtml() : e($line) !!}</p>
    @endforeach
    @isset($actionText)
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;"><tr><td style="border-radius:8px;background:{{ $brand->button }};">
        <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 22px;font-size:16px;font-weight:600;color:{{ $brand->onButton }};text-decoration:none;border-radius:8px;">{{ $actionText }}</a>
      </td></tr></table>
    @endisset
    @foreach ($outroLines as $line)
      <p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#55615F;">{!! $line instanceof \Illuminate\Contracts\Support\Htmlable ? $line->toHtml() : e($line) !!}</p>
    @endforeach
    @if (! empty($salutation))
      <p style="margin:18px 0 0;font-size:15px;color:#2F3B3E;">{{ $salutation }}</p>
    @endif
    @isset($actionText)
      <p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #E3E1DC;font-size:12px;line-height:1.5;color:#6B7472;word-break:break-all;">
        Se o botão não abrir, copie este endereço no navegador:<br><a href="{{ $actionUrl }}" style="color:{{ $brand->link }};">{{ $displayableActionUrl }}</a>
      </p>
    @endisset
  </td></tr>
  <tr><td align="center" style="padding:18px 8px;font-size:12px;color:#6B7472;">
    @if ($brand->footerUrl)
      <a href="{{ $brand->footerUrl }}" style="color:#6B7472;text-decoration:none;">{{ $brand->footer }}</a>
    @else
      {{ $brand->footer }}
    @endif
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
