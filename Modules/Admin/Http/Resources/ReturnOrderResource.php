<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'notes' => $this->notes,

            'products' => $this->getItems(),
            'extra_items' => $this->extra_items,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_percentage' => $this->discount_percentage,
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'oldOrder' => $this->order,
            'total' => $this->total,
            'status' => $this->status,
            'attachments' => MediaResource::collection($this->getMedia()),
            'is_migrated' => $this->is_migrated,
            'migrated_at' => $this->migrated_at,
            'tax_amount' => $this->tax_amount,
            'fatora_status' => $this->fatora_status,
            'qr_code' => $this->qr_code,
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->pivot->name,
                'sku' => $product->sku,
                'quantity' => $product->pivot->quantity,
                'returned_quantity' => $product->pivot->returned_quantity,
                'number' => $product->pivot->number,
                'product_name' => $product->pivot->product_name,
                'price' => $product->pivot->price,
                'discount' => $product->pivot->discount,
                'real_price' => $product->pivot->real_price,
                'normal_price' => $product->calcPrice(),
                'min_price' => $product->calcMinPrice(),
                'image' => $product->getFirstMediaUrl(),
                'location' => $product->location
            ];
        }
        return $items;
    }

}
