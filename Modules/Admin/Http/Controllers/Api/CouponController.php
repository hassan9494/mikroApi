<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Resources\CouponResource;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use App\Models\User;
use App\Services\CouponService;

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

    /**
     * @param $id
     * @return CouponResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return new CouponResource($model);
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
    public function store(): JsonResponse
    {
        $data = $this->validate();
        $invoice = $this->repository->make($data);
        return $this->success([
            'id' => $invoice->id
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $invoice = $this->repository->saveCoupon($id, $data);
        return $this->success();
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
     * Get valid coupons for a specific user and products
     */
    public function validCoupons(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'products' => 'nullable|array',
        ]);

        $userId = $request->input('user_id');
        $products = $request->input('products', []);
        $user = $userId ? User::find($userId) : null;

        $couponService = app(CouponService::class);

        // Get all active coupons
        $allCoupons = $this->repository->allActive();

        $validCoupons = [];

        foreach ($allCoupons as $coupon) {
            if ($request->input('order_id')){
                $validation = $couponService->validateForUpdate($coupon, $user, $products);
            }else{
                $validation = $couponService->validate($coupon, $user, $products);
            }

            if ($validation['valid']) {
                $validCoupons[] = $coupon;
            }
        }

        return $this->success(CouponResource::collection($validCoupons));
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
            'count_per_user' => 'required|numeric',
            'active' => 'required|boolean',
            'apply_count' => 'required|boolean',
            'apply_count_per_user' => 'required|boolean',
            'products.*.id' => 'exists:products,id',
            'brands.*.id' => 'exists:brands,id',
        ]);
    }
}
