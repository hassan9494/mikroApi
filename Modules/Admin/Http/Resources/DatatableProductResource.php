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
            'name' => $this->name,
            'stock' => $this->stock,
            'slug' => $this->sku,
            'price' => $this->price,
            'image' => $this->getFirstMediaUrl(),
        ];

    }
}
