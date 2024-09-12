<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

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

        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        if ((isset($user) && $user->hasRole(['Distributer']))){
            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'title' => $this->name,
                'slug' => $this->slug,
                'availableQty' => $this->stock,
                'is_available' => $this->options->available,
                'price' =>  $this->price->normal_price,
                'sale_price' => $this->price->distributor_price ?: null,
                'image' => $image,
                'sales' => $this->sales(null, null),

            ];
        }else{
            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'title' => $this->name,
                'slug' => $this->slug,
                'availableQty' => $this->stock,
                'is_available' => $this->options->available,
                'price' =>  $this->price->normal_price,
                'sale_price' => $this->price->sale_price ?: null,
                'image' => $image,
                'sales' => $this->sales(null, null),

            ];
        }

    }
}
