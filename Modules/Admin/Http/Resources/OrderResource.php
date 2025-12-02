<?php

namespace Modules\Admin\Http\Resources;

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
            'user_id' => $this->user_id,
            'tax_exempt_id' => $this->tax_exempt_id,
            'cashier_id' => $this->cashier_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],
            'taxExempt' => [
                'id' => $this->taxExempt?->id,
                'name' => $this->taxExempt?->name,
                'email' => $this->taxExempt?->email,
                'phone' => $this->taxExempt?->phone,
            ],
            'cashier' => [
                'id' => $this->cashier?->id,
                'name' => $this->cashier?->name,
                'email' => $this->cashier?->email,
                'phone' => $this->cashier?->phone,
            ],
            'number' => $this->number,
            'customer' => $this->customer,
            'notes' => $this->notes,
            'invoice_notes' => $this->invoice_notes,
            'pending' => $this->pending,

            'products' => $this->getItems(),
            'extra_items' => $this->extra_items,

            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
            'taxed_at' => $this->taxed_at,
            'updated_at' => $this->updated_at,

            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_percentage' => $this->discount_percentage,
            'uuid' => $this->uuid,
            'total' => $this->total,
            'coupon_discount' => $this->coupon_discount,

            'options' => $this->options,

            'shipping' => $this->shipping,
            'shipping_provider_id' => $this->shipping_provider_id,
            'shipping_provider' => $this->shippingProvider,
            'city_id' => $this->city_id,
            'coupon_id' => $this->coupon_id,

            'status' => $this->status,
            'tax_number' => $this->tax_number,
            'tax_amount' => $this->tax_amount,
            'attachments' => MediaResource::collection($this->getMedia()),
            'identity_number_type' => $this->identity_number_type,
            'customer_identity_number' => $this->customer_identity_number,
            'is_migrated' => $this->is_migrated,
            'fatora_status' => $this->fatora_status,
            'qr_code' => $this->qr_code,
            'histories' => OrderHistoryResource::collection($this->whenLoaded('histories')),
            'transaction' => TransactionResource::collection($this->transactions),
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
                        'brand_id' => $parentProduct->brand_id,
                        'location' => $parentProduct->location ?? '',
                        'stock_location' => $parentProduct->stock_location ?? '',
                        'stock' => $parentProduct->stock ?? 0,
                        'has_colors' => true,
                        'all_colors' => $parentProduct->product_variants->map(function($variant) {
                            return [
                                'id' => $variant->color_id,
                                'variant_id' => $variant->id,
                                'name' => $variant->name,
                                'image' => asset($variant->variant->getFirstMediaUrl()),
                                'stock' => $variant->stock,
                                'location' => $variant->location,
                                'price' => $variant->variant->price->sale_price ?? $variant->variant->normal_price
                            ];
                        })->toArray(), // Include all available colors
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
                    'brand_id' =>$variant ? $variant->variant->brand_id : $product->brand_id,
                    'location' => $variant->location ?? $product->location,
                    'stock' => $variant->stock ?? $product->stock,
                    'stock_location' => $product->stock_location
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
                    'brand_id' => $product->brand_id,
                    'stock' => $product->stock,
                    'stock_location' => $product->stock_location,
                    'has_colors' => false,
                    'colors' => [],
                    'all_colors' => [] // Empty for products without colors
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
