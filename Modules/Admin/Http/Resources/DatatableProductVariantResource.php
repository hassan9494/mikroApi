<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DatatableProductVariantResource extends JsonResource
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
            'variant_name' => $this->name,
            'name' => $this->variant->name,
            'short_description' => $this->variant->short_description,
            'stock' => $this->variant->stock,
            'min_qty' => $this->variant->min_qty,
            'price' => $this->variant->price,
            'image' => $this->variant->getFirstMediaUrl(),
            'product_id' => $this->product_id,
            'sku' => $this->variant->sku
//            'sales' => $this->completedOrders,
//            'brand_id' => $this->brand_id,
//            'source_id' => $this->source_id,
//            'variants_count' => $this->product_variants->count(),
        ];

    }
}
