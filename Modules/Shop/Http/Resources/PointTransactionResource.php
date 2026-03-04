<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PointTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'order_id' => $this->order_id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'is_expired' => $this->is_expired,
            'source' => $this->source,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relations
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'number' => $this->order->number,
                    'status' => $this->order->status,
                    'total' => $this->order->total,
                ];
            }),
        ];
    }
}
