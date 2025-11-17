<?php

namespace App\Services;

use Modules\Shop\Entities\Coupon;
use App\Models\User;
use Modules\Shop\Entities\Order;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate coupon against all rules
     */
    public function validate(Coupon $coupon, ?User $user = null, array $orderProducts = []): array
    {
        // Check basic validity
        if (!$coupon->active) {
            return ['valid' => false, 'message' => 'Coupon is not active'];
        }

        // Check dates
        $now = now();
        if ($coupon->start_at && $coupon->start_at->gt($now)) {
            return ['valid' => false, 'message' => 'Coupon has not started yet'];
        }
        if ($coupon->end_at && $coupon->end_at->lt($now)) {
            return ['valid' => false, 'message' => 'Coupon has expired'];
        }

        // Check global usage limit
        if ($coupon->apply_count && $coupon->use_count >= $coupon->count) {
            return ['valid' => false, 'message' => 'Coupon usage limit exceeded'];
        }

        // Check per-user usage limit
        if ($user && $coupon->apply_count_per_user) {
            $userUsage = $this->getUserCouponUsage($coupon, $user);

            if ($userUsage >= $coupon->count_per_user) {
                return ['valid' => false, 'message' => 'You have exceeded your usage limit for this coupon'];
            }
        }

        return ['valid' => true];
    }
    public function validateForUpdate(Coupon $coupon, ?User $user = null, array $orderProducts = []): array
    {
        // Check basic validity
        if (!$coupon->active) {
            return ['valid' => false, 'message' => 'Coupon is not active'];
        }

        // Check dates
        $now = now();
        if ($coupon->start_at && $coupon->start_at->gt($now)) {
            return ['valid' => false, 'message' => 'Coupon has not started yet'];
        }
        if ($coupon->end_at && $coupon->end_at->lt($now)) {
            return ['valid' => false, 'message' => 'Coupon has expired'];
        }

        // Check global usage limit
        if ($coupon->apply_count && $coupon->use_count > $coupon->count) {
            return ['valid' => false, 'message' => 'Coupon usage limit exceeded'];
        }

        // Check per-user usage limit
        if ($user && $coupon->apply_count_per_user) {
            $userUsage = $this->getUserCouponUsage($coupon, $user);

            if ($userUsage > $coupon->count_per_user) {
                return ['valid' => false, 'message' => 'You have exceeded your usage limit for this coupon'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Calculate discount considering product/brand exclusions
     */
    public function calculateDiscount(Coupon $coupon, array $orderProducts, array $extraItems = []): array
    {
        $eligibleSubtotal = 0;
        $excludedSubtotal = 0;

        // Get excluded product and brand IDs
        $excludedProductIds = $coupon->products->pluck('id')->toArray();
        $excludedBrandIds = $coupon->brands->pluck('id')->toArray();

        // Calculate totals for eligible and excluded items
        foreach ($orderProducts as $product) {
            if (!isset($product['id']) || !isset($product['price']) || !isset($product['quantity'])) {
                continue;
            }

            $productTotal = $product['price'] * $product['quantity'];

            // Check if product is excluded
            $isExcluded = in_array($product['id'], $excludedProductIds) ||
                (isset($product['brand_id']) && in_array($product['brand_id'], $excludedBrandIds));

            if ($isExcluded) {
                $excludedSubtotal += $productTotal;
            } else {
                $eligibleSubtotal += $productTotal;
            }
        }

        // Add extra items (always eligible for fixed coupons)
        foreach ($extraItems as $item) {
            if (!isset($item['price']) || !isset($item['quantity'])) {
                continue;
            }
            $itemTotal = $item['price'] * $item['quantity'];
            $eligibleSubtotal += $itemTotal;
        }

        $totalSubtotal = $eligibleSubtotal + $excludedSubtotal;

        // Calculate discount based on coupon type
        if ($coupon->is_percentage) {
            // Percentage coupons only apply to eligible items
            $discount = ($coupon->amount / 100) * $eligibleSubtotal;
        } else {
            // Fixed coupons apply to entire order
            $discount = min($coupon->amount, $totalSubtotal);
        }

        return [
            'discount' => $discount,
            'eligible_subtotal' => $eligibleSubtotal,
            'excluded_subtotal' => $excludedSubtotal,
            'total_subtotal' => $totalSubtotal,
            'excluded_product_ids' => $excludedProductIds,
            'excluded_brand_ids' => $excludedBrandIds
        ];
    }

    /**
     * Get user's coupon usage count
     */
    private function getUserCouponUsage(Coupon $coupon, User $user): int
    {
        // Count from coupon_users pivot table
        $pivotUsage = $coupon->users()->where('user_id', $user->id)->count();

        // Also count from orders (for existing orders)
        $orderUsage = Order::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->count();

        return max($pivotUsage, $orderUsage);
    }

    /**
     * Increment coupon usage
     */
    public function incrementUsage(Coupon $coupon, ?User $user = null,Order $order): void
    {
        DB::transaction(function () use ($coupon, $user,$order) {
            // Increment global usage
            $coupon->increment('use_count');

            // Track per-user usage
            if ($user && $coupon->apply_count_per_user) {
                $coupon->users()->syncWithoutDetaching([$user->id => ['used_at' => now(),'order_id'=>$order->id]]);
            }
        });
    }

    /**
     * Validate and apply coupon to order
     */
    public function applyToOrder(Coupon $coupon, Order $order): array
    {
        // Validate coupon
        $validation = $this->validateForUpdate($coupon, $order->user, $order->products->toArray());
        if (!$validation['valid']) {
            return $validation;
        }

        // Prepare products for calculation
        $orderProducts = $order->products->map(function ($product) {
            return [
                'id' => $product->id,
                'brand_id' => $product->brand_id,
                'price' => $product->pivot->price,
                'quantity' => $product->pivot->quantity
            ];
        })->toArray();

        $extraItems = $order->extra_items ?? [];

        // Calculate discount
        $calculation = $this->calculateDiscount($coupon, $orderProducts, $extraItems);

        // Increment usage
        $this->incrementUsage($coupon, $order->user,$order);

        return [
            'valid' => true,
            'discount' => $calculation['discount'],
            'calculation' => $calculation
        ];
    }
}
