<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Shop\Entities\Product;

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
        if (isset($user) && $user->hasRole(['Distributer'])){
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
                'sale_price' => $this->price->distributor_price ?: null,
                'description' => $this->convertOembedToIframe($this->description),
                'short_description' => $this->short_description,
                'short_description_ar' => $this->short_description_ar ?? '',
                'casher_note' => $this->casher_note ?? '',
                'packageInclude' => $this-> convertOembedToIframe($this->packageInclude),
                'features' => $this->convertOembedToIframe($this->features),
                'documents' => $this->convertOembedToIframe($this->documents),
                'code' => $this->convertOembedToIframe($this->code),

                'datasheets' => $this->datasheets ?? [],
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
                'replacement_item' =>  new ProductResource($replacement_item),
                'hasVariants' =>  $this->hasVariants,
                'deleted_at' =>  $this->deleted_at,
                'colors' => ProductVariantsResource::collection($this->product_variants),
                'kit' => $this->getKitItems(),
            ];
        }else {
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
                'description' => $this->convertOembedToIframe($this->description),
                'short_description' => $this->short_description,
                'short_description_ar' => $this->short_description_ar ?? '',
                'casher_note' => $this->casher_note ?? '',
                'packageInclude' => $this-> convertOembedToIframe($this->packageInclude),
                'features' => $this->convertOembedToIframe($this->features),
                'documents' => $this->convertOembedToIframe($this->documents),
                'code' => $this->convertOembedToIframe($this->code),
                'datasheets' => $this->datasheets ?? [],
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
                'replacement_item' =>  new ProductResource($replacement_item),
                'hasVariants' =>  $this->hasVariants,
                'deleted_at' =>  $this->deleted_at,
                'colors' => ProductVariantsResource::collection($this->product_variants),
                'kit' => $this->getKitItems(),
            ];
        }

    }
    /**
     * Get formatted kit items
     *
     * @return array
     */
    private function getKitItems(): array
    {
        // Use the same approach as the admin resource
        $kitProducts = $this->kit()->get();

        if ($kitProducts->isEmpty()) {
            return [];
        }

        return $kitProducts->map(function ($kitProduct) {
            $media = $kitProduct->getMedia();
            $image = count($media) > 0 ? $media[0]->getFullUrl() : '';

            return [
                'id' => $kitProduct->id,
                'name' => $kitProduct->name,
                'title' => $kitProduct->name,
                'quantity' => $kitProduct->pivot->quantity,
                'image' => $image,
                'slug' => $kitProduct->slug,
                'price' => $kitProduct->price->normal_price ?? 0,
                'sale_price' => $kitProduct->price->sale_price ?? null,
            ];
        })->toArray();
    }

    private function convertOembedToIframe($content)
    {
        if (empty($content)) {
            return $content;
        }

        // Convert oembed tags to iframes for YouTube
        $content = preg_replace_callback(
            '/<figure class="media"><oembed url="([^"]+)"><\/oembed><\/figure>/',
            function ($matches) {
                $url = $matches[1];

                // Handle YouTube URLs
                if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
                    $videoId = '';

                    // Extract video ID from different YouTube URL formats
                    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $videoMatches)) {
                        $videoId = $videoMatches[1];
                    } elseif (preg_match('/youtu\.be\/([^?]+)/', $url, $videoMatches)) {
                        $videoId = $videoMatches[1];
                    }

                    if ($videoId) {
                        return '<div class="video-container" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 20px 0;">
                        <iframe
                            src="https://www.youtube.com/embed/' . $videoId . '"
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                            allowfullscreen
                            title="YouTube Video">
                        </iframe>
                    </div>';
                    }
                }

                // Return original if we can't process it
                return $matches[0];
            },
            $content
        );

        return $content;
    }

}

