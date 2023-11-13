<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSalesReportResource extends JsonResource
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
            'name' => $this->name,
            'quantity' => $this->stock,
            'slug' => $this->sku,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
            'sales' => $this->sales(null, null),
        ];

    }
}
