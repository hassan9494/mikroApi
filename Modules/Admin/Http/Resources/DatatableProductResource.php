<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DatatableProductResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'source_sku' => $this->source_sku,
            'name' => $this->name,
            'stock' => $this->stock,
            'min_qty' => $this->min_qty,
            'slug' => $this->sku,
            'link' => $this->slug,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'sales' => $this->completedOrders,
            'brand_id' => $this->brand_id,
            'source_id' => $this->source_id,
            'stock_available' => $this->stock_available,
            'store_available' => $this->store_available,
            'order_qty' => $this->order_qty,
            'purchases_qty' => $this->purchases_qty,
            'meta' => $this->meta,
            'location' => $this->location,
            'stock_location' => $this->stock_location,
            'variants_count' => $this->product_variants->count(),
            'parent' => $this->parent?->product?->name,
        ];

    }
}
