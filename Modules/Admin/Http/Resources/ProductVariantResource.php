<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shop\Entities\Product;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductVariantResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $replacement_item = Product::where('id',$this->replacement_item)->first();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => $this->stock,
            'price' => $this->price,
            'short_description' => $this->short_description,
            'code' => $this->code,
            'min_qty' => $this->min_qty,

            'image' => asset($this->getFirstMediaUrl()),
            'media' => MediaResource::collection($this->getMedia()),
            'options' => $this->options,
            'maxCartAmount' => $this->maxCartAmount,
            'is_retired' => $this->is_retired,

        ];
    }


}
