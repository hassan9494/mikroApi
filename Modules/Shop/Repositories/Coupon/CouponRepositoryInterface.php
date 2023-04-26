<?php

namespace Modules\Shop\Repositories\Coupon;

use App\Repositories\Base\BaseRepository;

/**
 * Interface CouponRepository
 * @package Modules\Shop\Repositories\Coupon
 */
interface CouponRepositoryInterface extends BaseRepository
{

    /**
     * @param $id
     * @return mixed
     */
    public function check($id): mixed;

    /**
     * @param $id
     * @return mixed
     */
    public function checkOrFail($id): mixed;

    /**
     * @param $code
     * @return mixed
     */
    public function checkByCodeOrFail($code): mixed;

}
