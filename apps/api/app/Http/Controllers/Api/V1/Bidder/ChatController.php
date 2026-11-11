<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bidder;

use DoleLeilao\Chat\Application\Author;
use DoleLeilao\Chat\Application\PostMessage;
use DoleLeilao\Identity\Infrastructure\BidderUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Participante escreve no chat do auditório (RF-F05, RF-F06). */
final class ChatController
{
    public function store(Request $request, string $auction, PostMessage $post): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'reply_to_id' => ['nullable', 'string', 'size:26'],
        ], [], ['body' => 'a mensagem']);
        /** @var BidderUser $bidder */
        $bidder = $request->user('sanctum');

        $message = $post->execute($auction, Author::bidder($bidder->id), $request->string('body')->value(), $request->input('reply_to_id') === null ? null : $request->string('reply_to_id')->value());

        return response()->json($message, 201);
    }
}
