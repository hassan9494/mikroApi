<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],
            'number' => $this->number,
            'customer' => $this->customer,
            'notes' => $this->notes,
            'invoice_notes' => $this->invoice_notes,

            'products' => $this->getItems(),
            'extra_items' => $this->extra_items,

            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'taxed_at' => $this->taxed_at,

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'coupon_discount' => $this->coupon_discount,

            'options' => $this->options,

            'shipping' => $this->shipping,
            'shipping_provider_id' => $this->shipping_provider_id,
            'city_id' => $this->city_id,
            'coupon_id' => $this->coupon_id,

            'status' => $this->status,
            'tax_number' => $this->tax_number,
            'tax_amount' => $this->tax_amount,

            'attachments' => MediaResource::collection($this->getMedia()),
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $product->pivot->quantity,
                'price' => $product->pivot->price,
                'normal_price' => $product->calcPrice(),
                'min_price' => $product->calcMinPrice(),
                'image' => $product->getFirstMediaUrl()
            ];
        }
        return $items;
    }

}
