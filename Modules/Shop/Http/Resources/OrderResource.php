<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;

class OrderResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'items' => $this->getItems(),
            'shipping' => $this->shipping,
            'customer' => $this->customer,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'coupon_discount' => $this->coupon_discount,
            'total' => $this->total,
            'date' => $this->created_at,
            'status' => $this->status,
        ];
    }

    private function getItems()
    {
        $items = [];

        // Group products by their parent product (for color variants)
        $groupedProducts = [];

        foreach ($this->products as $product) {
            $pivot = $product->pivot;

            if ($pivot->is_color && $pivot->color_id) {
                // This is a color variant - group under parent product
                $parentProductId = $this->findParentProductId($pivot->color_id);

                if (!isset($groupedProducts[$parentProductId])) {
                    $parentProduct = Product::with('product_variants')->find($parentProductId);
                    $groupedProducts[$parentProductId] = [
                        'id' => $parentProductId,
                        'name' => $parentProduct->name ?? 'Parent Product',
                        'sku' => $parentProduct->sku ?? '',
                        'product_name' => $parentProduct->name ?? 'Parent Product',
                        'normal_price' => $parentProduct->calcPrice() ?? 0,
                        'min_price' => $parentProduct->calcMinPrice() ?? 0,
                        'image' => $parentProduct->getFirstMediaUrl() ?? '',
                        'location' => $parentProduct->location ?? '',
                        'stock' => $parentProduct->stock ?? 0,
                        'has_colors' => true,
                        'colors' => []
                    ];
                }

                // Add the color variant with order-specific data
                $variant = ProductVariant::find($pivot->color_id);
                $groupedProducts[$parentProductId]['colors'][] = [
                    'id' => $variant->color_id,
                    'variant_id' => $pivot->color_id,
                    'name' => $variant->name ?? $pivot->product_name,
                    'quantity' => $pivot->quantity,
                    'number' => $pivot->number,
                    'price' => $pivot->price,
                    'discount' => $pivot->discount,
                    'real_price' => $pivot->real_price,
                    'image' => $variant ? asset($variant->variant->getFirstMediaUrl()) : $product->getFirstMediaUrl(),
                    'location' => $variant->location ?? $product->location,
                    'stock' => $variant->stock ?? $product->stock
                ];
            } else {
                // Regular product without colors
                $items[] = [
                    'id' => $product->id,
                    'name' => $pivot->product_name,
                    'sku' => $product->sku,
                    'quantity' => $pivot->quantity,
                    'number' => $pivot->number,
                    'product_name' => $pivot->product_name,
                    'price' => $pivot->price,
                    'discount' => $pivot->discount,
                    'real_price' => $pivot->real_price,
                    'normal_price' => $product->calcPrice(),
                    'min_price' => $product->calcMinPrice(),
                    'image' => $product->getFirstMediaUrl(),
                    'location' => $product->location,
                    'stock' => $product->stock,
                    'has_colors' => false,
                    'colors' => [],
                ];
            }
        }

        // Add the grouped products (with colors) to the items array
        foreach ($groupedProducts as $parentProduct) {
            $items[] = $parentProduct;
        }

        return $items;
    }

    /**
     * Find the parent product ID for a color variant
     */
    private function findParentProductId($colorVariantId)
    {
        $variant = ProductVariant::find($colorVariantId);
        return $variant ? $variant->product_id : null;
    }

}
