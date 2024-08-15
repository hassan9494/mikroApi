<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admin\Http\Resources\MediaResource;

class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'availableQty' => $this->stock,
            'price' => $this->price->normal_price,
            'sale_price' => $this->price->sale_price ?: null,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'features' => $this->features,
            'documents' => $this->documents,
            'image' => $image,
            'gallery' => MediaResource::collection($media),
            'categories' => $this->categories->map(function($e) {
                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                ];
            }),
            'brand'=>$this->brand != null ? $this->brand : null,
            'source'=>$this->source != null ? $this->source : null,
        ];
    }
}
