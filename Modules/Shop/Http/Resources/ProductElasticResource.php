<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Shop\Entities\Product;

class ProductElasticResource extends JsonResource
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
        $replacement_item = Product::where('id',$this->replacement_item)->first();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';

            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'title' => $this->name,
                'location' => $this->location,
                'meta_title' => $this->meta->title,
                'meta_keyword' => $this->meta->keywords,
                'meta_description' => $this->meta->description,
                'stock_location' => $this->stock_location,
                'slug' => $this->slug,
                'availableQty' => $this->stock,
                'is_available' => $this->options->available,
                'is_retired' => $this->is_retired,
                'price' => $this->price->normal_price,
                'sale_price' => $this->price->sale_price ?: null,
                'description' => $this->description,
                'short_description' => $this->short_description,
                'short_description_ar' => $this->short_description_ar ?? '',
                'packageInclude' => $this->packageInclude,
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
                'replacement_item' =>  new ProductElasticResource($replacement_item),
                'hasVariants' =>  $this->hasVariants,
                'deleted_at' =>  $this->deleted_at,
                'colors' => ProductVariantsElasticResource::collection($this->product_variants)
            ];


    }
}
