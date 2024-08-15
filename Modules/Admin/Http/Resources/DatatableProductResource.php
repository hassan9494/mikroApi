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
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'sales' => $this->completedOrders,
            'brand_id' => $this->brand_id,
            'source_id' => $this->source_id,
        ];

    }
}
