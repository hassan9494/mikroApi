<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Shop\Entities\Product;

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
        $token = $request->bearerToken();

        if ($token) {
            // Find the token and authenticate the user
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                Auth::login($user);
            }
        }
        $user = auth()->user();
        $media = $this->getMedia();
        $replacement_item = Product::where('id',$this->replacement_item)->first();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        if ((isset($user) && $user->hasRole(['Distributer']))){
            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'title' => $this->name,
                'slug' => $this->slug,
                'availableQty' => $this->stock,
                'is_available' => $this->options->available,
                'is_retired' => $this->is_retired,
                'location' => $this->location,
                'stock_location' => $this->stock_location,
                'price' =>  $this->price->normal_price,
                'sale_price' => $this->price->distributor_price ?: null,
                'image' => $image,
                'sales' => $this->sales(null, null),
                'replacement_item' =>  new ProductResource($replacement_item),
                'hasVariants' =>  $this->hasVariants,
                'colors' => ProductVariantsResource::collection($this->product_variants)

            ];
        }else{
            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'title' => $this->name,
                'slug' => $this->slug,
                'availableQty' => $this->stock,
                'is_available' => $this->options->available,
                'is_retired' => $this->is_retired,
                'location' => $this->location,
                'stock_location' => $this->stock_location,
                'price' =>  $this->price->normal_price,
                'sale_price' => $this->price->sale_price ?: null,
                'image' => $image,
                'sales' => $this->sales(null, null),
                'replacement_item' =>  new ProductResource($replacement_item),
                'hasVariants' =>  $this->hasVariants,
                'colors' => ProductVariantsResource::collection($this->product_variants)

            ];
        }

    }
}
