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
            'name' => $this->name,
            'short_description' => $this->short_description,
            'stock' => $this->stock,
            'min_qty' => $this->min_qty,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'product_id' => $this->product_id
//            'sales' => $this->completedOrders,
//            'brand_id' => $this->brand_id,
//            'source_id' => $this->source_id,
//            'variants_count' => $this->product_variants->count(),
        ];

    }
}
