<?php

namespace Modules\Shop\Observers;


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
        }

        $order->onSaving();
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
