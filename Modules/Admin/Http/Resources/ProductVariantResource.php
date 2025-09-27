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
        $selectedProduct = Product::where('id', $this->variant->id)->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_name' => $this->variant->name,
            'stock' => $this->variant->stock,
            'price' => $this->variant->price,
            'short_description' => $this->variant->short_description,
            'code' => $this->variant->code,
            'min_qty' => $this->variant->min_qty,

            'image' => asset($this->variant->getFirstMediaUrl()),
            'media' => MediaResource::collection($this->variant->getMedia()),
            'options' => $this->variant->options,
            'maxCartAmount' => $this->variant->maxCartAmount,
            'is_retired' => $this->variant->is_retired,
            'selected_product_id' => $this->variant->id,
            'selected_product' => $selectedProduct ? [
                'id' => $selectedProduct->id,
                'name' => $selectedProduct->name,
                'sku' => $selectedProduct->sku,
                'image' => asset($selectedProduct->getFirstMediaUrl()),
                'stock' => $selectedProduct->stock,
                'price' => $selectedProduct->price
            ] : null

        ];
    }
}
