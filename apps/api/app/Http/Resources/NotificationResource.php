<?php

declare(strict_types=1);

namespace App\Http\Resources;

use DoleLeilao\Notification\Application\NotificationView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationView */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NotificationView $n */
        $n = $this->resource;

        return [
            'id' => $n->id,
            'kind' => $n->kind,
            'title' => $n->title,
            'body' => $n->body,
            'url' => $n->url,
            'read' => $n->readAt !== null,
            'created_at' => $n->createdAt->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}
