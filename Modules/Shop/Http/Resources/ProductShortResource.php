<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductShortResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $media = $this->getMedia();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->name,
            'slug' => $this->sku,
            'availableQty' => $this->stock,
            'price' => $this->price->normal_price,
            'sale_price' => $this->price->sale_price ?: null,
            'image' => $image,
        ];
    }
}
