<?php

namespace Modules\Shop\Observers;


use App\Services\PointService;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PointTransaction;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Modules\Shop\Support\Enums\OrderShippingStatus;
use Modules\Shop\Support\Enums\OrderStatus;

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

        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;

            // Order is becoming COMPLETED
            if ($order->isCompleted) {
                if ($order->shipping) {
                    $shipping = json_decode(json_encode($order->shipping), true);
                    $shipping['status'] = OrderShippingStatus::DELIVERED();
                    $order->shipping = $shipping;
                }

                // Award points when order is completed (only for registered users)
                $this->awardPointsOnCompletion($order);

                // Note: Points spending now happens at order creation time, not here
            }

            // Order is changing FROM COMPLETED to another status
            if ($oldStatus === OrderStatus::COMPLETED()->value && !$order->isCompleted) {
                // Reverse earned points (earn logic unchanged)
                $this->handlePointsOnStatusChangeFromCompleted($order);
            }

            // Order is being CANCELLED - refund spent points (from any previous status)
            if ($newStatus === OrderStatus::CANCELED()->value && $oldStatus !== OrderStatus::CANCELED()->value) {
                $this->refundSpentPointsOnCancel($order);
            }
        }

        $order->onSaving();
    }

    /**
     * Deduct points when order is completed (for points_used)
     *
     * @param Order $order
     * @return void
     */
    private function deductPointsOnCompletion(Order $order): void
    {
        // Only deduct points if user has points to use
        if (!$order->user_id || !$order->points_used || $order->points_used <= 0) {
            return;
        }

        // Only deduct points for regular users (not employees)
        $user = \App\Models\User::find($order->user_id);
        if (!$user) {
            return;
        }

        $userRoles = $user->getRoleNames();
        if ($userRoles->count() !== 1 || !$userRoles->contains('user')) {
            return;
        }

        try {
            $pointService = app(PointService::class);

            // Check if points system is enabled
            if (!$pointService->isEnabled()) {
                return;
            }

            // Check if points have already been deducted for this order (and not refunded)
            // This prevents duplicate deduction when status changes to COMPLETED multiple times
            // Count SPEND transactions vs REFUND transactions for this order
            $spendCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_SPEND)
                ->count();
            $refundCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_REFUND)
                ->count();

            // If there are more SPENDs than REFUNDs, points are already deducted for this order
            if ($spendCount > $refundCount) {
                // Points already deducted and not refunded, skip
                return;
            }

            // Deduct points
            $pointService->usePoints(
                $order->user_id,
                $order->points_used,
                $order->id,
                "Redeemed for order #{$order->id}"
            );
        } catch (\Exception $e) {
            // Log error but don't fail the order completion
            \Log::error('Failed to deduct points for order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle points when order status changes from COMPLETED to another status
     * Refunds spent points and reverses earned points
     *
     * @param Order $order
     * @return void
     */
    private function handlePointsOnStatusChangeFromCompleted(Order $order): void
    {
        if (!$order->user_id) {
            return;
        }

        // Only process for regular users (not employees)
        $user = \App\Models\User::find($order->user_id);
        if (!$user) {
            return;
        }

        $userRoles = $user->getRoleNames();
        if ($userRoles->count() !== 1 || !$userRoles->contains('user')) {
            return;
        }

        try {
            $pointService = app(PointService::class);

            if (!$pointService->isEnabled()) {
                return;
            }

            // Note: Spent points are NOT refunded here - they were deducted at order creation
            // and will be refunded when the order is cancelled (see refundSpentPointsOnCancel).

            // Reverse earned points (remove points_earned from user)
            // Only reverse if there are more EARNs than ADJUSTs (active earn exists)
            if ($order->points_earned && $order->points_earned > 0) {
                $earnCount = PointTransaction::where('order_id', $order->id)
                    ->where('type', PointTransaction::TYPE_EARN)
                    ->count();
                $adjustCount = PointTransaction::where('order_id', $order->id)
                    ->where('type', PointTransaction::TYPE_ADJUST)
                    ->count();

                if ($earnCount > $adjustCount) {
                    $pointService->reverseEarnedPointsForOrder($order->id);
                    // Reset points_earned on the order
                    $order->points_earned = 0;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to handle points on status change for order ' . $order->id . ': ' . $e->getMessage());
        }
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

        // Only award points to users with ONLY the 'user' role (not employees)
        // If user has any other role (admin, cashier, etc.), they should not earn points
        $user = \App\Models\User::find($order->user_id);
        if (!$user) {
            return;
        }

        $userRoles = $user->getRoleNames();
        if ($userRoles->count() !== 1 || !$userRoles->contains('user')) {
            return;
        }

        try {
            $pointService = app(PointService::class);

            // Check if points system is enabled
            if (!$pointService->isEnabled()) {
                return;
            }

            // Check if points have already been awarded for this order (and not reversed)
            // This prevents duplicate awarding when status changes to COMPLETED multiple times
            // Count EARN transactions vs ADJUST (reversal) transactions for this order
            $earnCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_EARN)
                ->count();
            $adjustCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_ADJUST)
                ->count();

            // If there are more EARNs than ADJUSTs, points are already active for this order
            if ($earnCount > $adjustCount) {
                // Points already awarded and not reversed, skip
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
     * Refund spent points when an order is cancelled.
     * Points were deducted at order creation time and need to be returned on cancellation.
     *
     * @param Order $order
     * @return void
     */
    private function refundSpentPointsOnCancel(Order $order): void
    {
        if (!$order->user_id || !$order->points_used || $order->points_used <= 0) {
            return;
        }

        try {
            $pointService = app(PointService::class);

            if (!$pointService->isEnabled()) {
                return;
            }

            // Check if points have already been refunded (prevent double refund)
            $spendCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_SPEND)
                ->count();
            $refundCount = PointTransaction::where('order_id', $order->id)
                ->where('type', PointTransaction::TYPE_REFUND)
                ->count();

            if ($spendCount > $refundCount) {
                $pointService->refundSpentPointsForOrder($order->id);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to refund spent points on order cancellation ' . $order->id . ': ' . $e->getMessage());
        }
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
