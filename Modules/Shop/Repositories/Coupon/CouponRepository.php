<?php

namespace Modules\Shop\Repositories\Coupon;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Coupon;

/**
 * Class EloquentCoupon
 * @package Modules\Shop\Repositories\Coupon
 */
class CouponRepository extends EloquentRepository implements CouponRepositoryInterface
{

    /**
     * EloquentCoupon constructor.
     * @param Coupon $model
     */
    public function __construct(Coupon $model)
    {
        parent::__construct($model);
    }



    private function valid($coupon)
    {
        if (
            !$coupon || ($coupon->orders()->count() >= $coupon->count) || !$coupon->valid
        ) abort(400, 'error_invalid_coupon');
        return $coupon;
    }

    public function check($id): mixed
    {
        $coupon = $this->findOrFail($id);
        if ($coupon->orders()->count() >= $coupon->count) return false;
        return $coupon->valid;
    }

    public function checkOrFail($id): mixed
    {
        $coupon = $this->model->where('id', '=', $id)->first();
        return $this->valid($coupon);
    }

    public function checkByCodeOrFail($code): mixed
    {
        $coupon = $this->model->where('code', '=', $code)->first();
        return $this->valid($coupon);
    }
}
