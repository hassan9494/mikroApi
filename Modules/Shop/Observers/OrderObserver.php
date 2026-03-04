<?php

namespace Modules\Shop\Observers;


use App\Services\PointService;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Order;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Modules\Shop\Support\Enums\OrderShippingStatus;

class OrderObserver
{

    /**
     * @var CouponRepositoryInterface
     */
    private CouponRepositoryInterface $couponRepository;

    /**
     * @param CouponRepositoryInterface $couponRepository
     */
    public function __construct(CouponRepositoryInterface $couponRepository)
    {
        $this->couponRepository = $couponRepository;
    }

    /**
     * @param Order $order
     */
    public function creating(Order $order)
    {

        $this->checkCoupon($order->coupon_id);
    }

    /**
     * @param Order $order
     */
    public function created(Order $order)
    {
        if ($order->coupon_id){
            $coupon = Coupon::find($order->coupon_id);
            $coupon->use_count += 1;
            $coupon->save();
        }

    }

    public function saving(Order $order)
    {

        if ($order->isDirty('coupon_id')) {
            $this->checkCoupon($order->coupon_id);
        }

        if (
            $order->shipping &&
            $order->isCompleted &&
            $order->isDirty('status')
        ) {
            $shipping = json_decode(json_encode($order->shipping), true);
            $shipping['status'] = OrderShippingStatus::DELIVERED();
            $order->shipping = $shipping;

            // Award points when order is completed (only for registered users)
            $this->awardPointsOnCompletion($order);
        }

        $order->onSaving();
    }

    /**
     * Award points when order is completed
     *
     * @param Order $order
     * @return void
     */
    private function awardPointsOnCompletion(Order $order): void
    {
        // Only award points to registered users
        if (!$order->user_id) {
            return;
        }

        try {
            $pointService = app(PointService::class);

            // Check if points system is enabled
            if (!$pointService->isEnabled()) {
                return;
            }

            // Calculate earnable total: subtotal - discount - points_discount (before shipping)
            // Points are earned on the final paid amount excluding shipping
            $shippingCost = ($order->shipping?->free ?? false) ? 0 : ($order->shipping?->cost ?? 0);
            $earnableTotal = $order->total - $shippingCost;

            if ($earnableTotal <= 0) {
                return;
            }

            // Calculate and award points
            $pointsToAward = $pointService->calculateEarnedPoints($earnableTotal);

            if ($pointsToAward > 0) {
                $pointService->awardPoints(
                    $order->user_id,
                    $pointsToAward,
                    $order->id,
                    'order',
                    "Earned from order #{$order->id}"
                );

                // Store points earned on the order
                $order->points_earned = $pointsToAward;
            }
        } catch (\Exception $e) {
            // Log error but don't fail the order completion
            \Log::error('Failed to award points for order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    /**
     * @param Order $order
     */
    public function updated(Order $order)
    {
        //
    }

    /**
     * @param Order $order
     */
    public function deleted(Order $order)
    {
        //
    }

    /**
     * @param Order $order
     */
    public function restored(Order $order)
    {
        //
    }

    /**
     * @param Order $order
     */
    public function forceDeleted(Order $order)
    {
        //
    }

    /**
     * @param $couponId
     * @param null $oldId
     */
    private function checkCoupon($couponId)
    {
        if ($couponId) {
            $this->couponRepository->checkOrFail($couponId);

        }
    }

}
