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
            'cashier_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],
            'cashier' => [
                'id' => $this->cashier?->id,
                'name' => $this->cashier?->name,
                'email' => $this->cashier?->email,
                'phone' => $this->cashier?->phone,
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
            'updated_at' => $this->updated_at,

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'coupon_discount' => $this->coupon_discount,

            'options' => $this->options,

            'shipping' => $this->shipping,
            'shipping_provider_id' => $this->shipping_provider_id,
            'shipping_provider' => $this->shippingProvider,
            'city_id' => $this->city_id,
            'coupon_id' => $this->coupon_id,

            'status' => $this->status,
            'tax_number' => $this->tax_number,
            'tax_amount' => $this->tax_amount,
            'attachments' => MediaResource::collection($this->getMedia()),
            'identity_number_type' => $this->identity_number_type,
            'customer_identity_number' => $this->customer_identity_number,
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->pivot->product_name,
                'sku' => $product->sku,
                'quantity' => $product->pivot->quantity,
                'number' => $product->pivot->number,
                'product_name' => $product->pivot->product_name,
                'price' => $product->pivot->price,
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
