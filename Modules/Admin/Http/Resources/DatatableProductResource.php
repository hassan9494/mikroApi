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

        $rawStock = $this->getAttributes()['stock'] ?? 0;
        $stock_available = $this->stock_available ?? 0;
        $store_available = $this->store_available ?? 0;

        // CRITICAL: Apply the same logic as ProductResource
        // If both available are 0 or null, but raw stock has value
        // Put all stock in store_available
        if (($stock_available == 0 || is_null($stock_available)) &&
            ($store_available == 0 || is_null($store_available)) &&
            $rawStock > 0) {
            $store_available = $rawStock;
            $stock_available = 0;
        }

        // Now calculate the actual stock value
        $stock = $stock_available + $store_available;

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
            'stock_available' => $stock_available,
            'store_available' => $store_available,
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
