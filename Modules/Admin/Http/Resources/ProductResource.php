<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
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
            'price' => $this->price,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'features' => $this->features,
            'code' => $this->code,
            'documents' => $this->documents,
            'min_qty' => $this->min_qty,

            'kit' => $this->kit()->get()->map(function ($e) {
                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'quantity' => $e->pivot->quantity,
                    'image' => $e->getFirstMediaUrl()
                ];
            }),

            'categories' => $this->categories()->get()->map(function ($e) {
                return $e->id;
            }),

            'image' => asset($this->getFirstMediaUrl()),
            'media' => MediaResource::collection($this->getMedia()),

            'meta' => $this->meta,
            'options' => $this->options,
            'datasheets' => $this->datasheets,
            'maxCartAmount' => $this->maxCartAmount,
            'brand_id' => $this->brand_id != null ? $this->brand_id : null,
            'source_id' => $this->source_id != null ? $this->source_id : null,

        ];
    }


}
