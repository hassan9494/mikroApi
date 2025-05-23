<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shop\Entities\Product;
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
        $replacement_item = Product::where('id',$this->replacement_item)->first();
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
            'related'=>$this->relatedProducts()->get()->map(function ($e) {
                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'image' => $e->getFirstMediaUrl()
                ];
            }),

            'categories' => $this->categories()->where('parent',0)->get()->map(function ($e) {
                return $e->id;
            }),

            'sub_categories' => $this->categories()->where('parent','!=',0)->get()->map(function ($e) {
                return $e->id;
            }),

            'image' => asset($this->getFirstMediaUrl()),
            'media' => MediaResource::collection($this->getMedia()),

            'meta' => $this->meta,
            'options' => $this->options,
            'datasheets' => $this->datasheets,
            'maxCartAmount' => $this->maxCartAmount,
            'packageInclude' => $this->packageInclude,
            'brand_id' => $this->brand_id != null ? $this->brand_id : null,
            'source_id' => $this->source_id != null ? $this->source_id : null,
            'is_retired' => $this->is_retired,
            'replacement_item' => $replacement_item ?[ new ProductResource($replacement_item)]  : [],
            'hasVariants' => $this->hasVariants,
            'location' => $this->location,
            'stock_location' => $this->stock_location,

        ];
    }


}
