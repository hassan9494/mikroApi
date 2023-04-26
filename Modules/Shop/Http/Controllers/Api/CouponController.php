<?php


namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;

class CouponController extends Controller
{

    use ApiResponser;

    /**
     * @var CouponRepositoryInterface
     */
    private CouponRepositoryInterface $coupons;

    /**
     * CouponController constructor.
     * @param CouponRepositoryInterface $coupons
     */
    public function __construct(
        CouponRepositoryInterface $coupons
    )
    {
        $this->coupons = $coupons;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required'
        ]);
        $coupon = $this->coupons->checkByCodeOrFail($data['code']);
        return $this->success([
            'id' => $coupon->id,
            'is_percentage' => $coupon->is_percentage,
            'amount' => $coupon->amount,
        ]);
    }

}
