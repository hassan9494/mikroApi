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

        // Get the raw stock value from database
        $rawStock = $this->getAttributes()['stock'] ?? 0;
        $stock_available = $this->stock_available ?? 0;
        $store_available = $this->store_available ?? 0;

        // CRITICAL: If both available are 0 or null, but raw stock has value
        // Put all stock in store_available for display
        if (($stock_available == 0 || is_null($stock_available)) &&
            ($store_available == 0 || is_null($store_available)) &&
            $rawStock > 0) {
            $store_available = $rawStock;
            $stock_available = 0;
        }

        // Now calculate the actual stock value
        $stock = $stock_available + $store_available;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'source_sku' => $this->source_sku,
            'name' => $this->name,
            'stock' => $this->stock,
            'stock_available' => $stock_available,
            'store_available' => $store_available,
            'price' => $this->price,
            'exchange_factor' => number_format($this->exchange_factor,3),
            'base_purchases_price' => number_format($this->base_purchases_price,3),
            'description' => $this->description,
            'short_description' => $this->short_description,
            'short_description_ar' => $this->short_description_ar,
            'casher_note' => $this->casher_note,
            'features' => $this->features,
            'code' => $this->code,
            'documents' => $this->documents,
            'min_qty' => $this->min_qty,
            'search_factor' => $this->search_factor,

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
            'media' => MediaResource::collection($this->getMedia()->sortBy('order_column')),

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
            'order_qty' => $this->order_qty,
            'is_show_for_search' => $this->is_show_for_search,
            'is_color_sun' => $this->is_color_sun,

        ];
    }


}
