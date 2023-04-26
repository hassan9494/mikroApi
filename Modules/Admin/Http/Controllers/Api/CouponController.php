<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;

class CouponController extends ApiAdminController
{

    /**
     * CouponRepository constructor.
     * @param CouponRepositoryInterface $repository
     */
    public function __construct(CouponRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }


    public function datatableSearchFields(): array
    {
        return [
            'id', 'code'
        ];
    }

    /**
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck();
        return $this->success($data);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'code' => 'required',
            'is_percentage' => 'required|boolean',
            'amount' => 'required|numeric',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
            'count' => 'required|numeric',
            'active' => 'required|boolean',
        ]);
    }

}
