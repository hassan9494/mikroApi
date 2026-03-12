<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockCountProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'stock_count_id' => $this->stock_count_id,
            'product_id' => $this->product_id,

            // Store (physical store count)
            'store_available_expected' => $this->store_available_expected,
            'store_available_counted' => $this->store_available_counted,
            'store_available_difference' => $this->store_available_difference,

            // Stock (warehouse count)
            'stock_available_expected' => $this->stock_available_expected,
            'stock_available_counted' => $this->stock_available_counted,
            'stock_available_difference' => $this->stock_available_difference,

            // Totals
            'total_expected' => $this->total_expected,
            'total_counted' => $this->total_counted,
            'total_difference' => $this->total_difference,

            'notes' => $this->notes,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'base_purchases_price' => $this->product->base_purchases_price,
                    'current_stock' => $this->product->stock,
                    'current_stock_available' => $this->product->stock_available,
                    'current_store_available' => $this->product->store_available,
                    'image' => $this->product->getFirstMediaUrl(),
                    'price' => $this->product->price,
                ];
            }),
        ];
    }
}
