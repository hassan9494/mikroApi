<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Shop\Entities\Product;

class ProductVariantsResource extends JsonResource
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
        $media = $this->variant->getMedia();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        if (isset($user) && $user->hasRole(['Distributer'])){
            return [
                'id' => $this->id,
                'color_id' => $this->variant->id,
                'title' => $this->name,
                'name' => $this->variant->name,
                'availableQty' => $this->variant->stock,
                'is_available' => $this->variant->options->available,
                'is_retired' => $this->variant->is_retired,
                'price' => $this->variant->price->normal_price,
                'location' => $this->variant->location,
                'sale_price' => $this->variant->price->distributor_price ?: null,
                'short_description' => $this->variant->short_description,
                'short_description_ar' => $this->variant->short_description_ar,
                'image' => $image,
                'gallery' => MediaResource::collection($media),
                'packageInclude' => $this->variant->packageInclude,
                'features' => $this->variant->features,
                'documents' => $this->variant->documents,
                'description' => $this->variant->description,
                'datasheets' => $this->variant->datasheets,
                'categories' => $this->variant->categories->map(function($e) {
                    return [
                        'id' => $e->id,
                        'title' => $e->title,
                        'slug' => $e->slug,
                    ];
                }),
            ];
        }else {
            return [
                'id' => $this->id,
                'color_id' => $this->variant->id,
                'title' => $this->name,
                'name' => $this->variant->name,
                'availableQty' => $this->variant->stock,
                'location' => $this->variant->location,
                'is_available' => $this->variant->options->available,
                'is_retired' => $this->variant->is_retired,
                'price' => $this->variant->price->normal_price,
                'sale_price' => $this->variant->price->sale_price ?: null,
                'short_description' => $this->variant->short_description,
                'short_description_ar' => $this->variant->short_description_ar,
                'image' => $image,
                'gallery' => MediaResource::collection($media),
                'packageInclude' => $this->variant->packageInclude,
                'features' => $this->variant->features,
                'documents' => $this->variant->documents,
                'description' => $this->variant->description,
                'datasheets' => $this->variant->datasheets,
                'categories' => $this->variant->categories->map(function($e) {
                    return [
                        'id' => $e->id,
                        'title' => $e->title,
                        'slug' => $e->slug,
                    ];
                })
            ];
        }

    }



}
