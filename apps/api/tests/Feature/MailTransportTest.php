<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

it('o mailer "sequenzy" conecta em smtp.sequenzy.com:587 com o usuário api e a chave como senha', function () {
    config(['mail.mailers.sequenzy.password' => 'seq_live_teste']);

    $transport = Mail::mailer('sequenzy')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    /** @var EsmtpTransport $transport */
    $stream = $transport->getStream();
    expect($stream)->toBeInstanceOf(SocketStream::class);
    /** @var SocketStream $stream */
    expect($stream->getHost())->toBe('smtp.sequenzy.com')
        ->and($stream->getPort())->toBe(587)
        ->and($stream->isTLS())->toBeFalse() // 587: começa sem TLS e sobe com STARTTLS
        ->and($transport->getUsername())->toBe('api')
        ->and($transport->getPassword())->toBe('seq_live_teste');
});

it('na porta 465 usa TLS desde a conexão', function () {
    config(['mail.mailers.sequenzy.port' => 465, 'mail.mailers.sequenzy.scheme' => 'smtps']);

    $stream = Mail::mailer('sequenzy')->getSymfonyTransport();
    /** @var EsmtpTransport $stream */
    expect($stream->getStream())->toBeInstanceOf(SocketStream::class);
    /** @var SocketStream $socket */
    $socket = $stream->getStream();
    expect($socket->isTLS())->toBeTrue();
});
