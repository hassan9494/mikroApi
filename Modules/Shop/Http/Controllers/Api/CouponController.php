<?php


namespace Modules\Shop\Http\Controllers\Api;

use App\Services\CouponService;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Validate coupon for cart
     */
    public function validateForCart(Request $request): JsonResponse
    {

        $request->validate([
            'coupon_code' => 'required|string',
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.brand_id' => 'nullable',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.name' => 'sometimes|string',
        ]);
//        dd(Auth::user());
        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon not found']);
        }

        $user = auth()->user();
        $couponService = app(CouponService::class);

        // Validate coupon
        $validation = $couponService->validate($coupon, $user, $request->items);

        if (!$validation['valid']) {
            return response()->json($validation);
        }

        // Calculate discount for the cart
        $calculation = $couponService->calculateDiscount($coupon, $request->items);

        // Prepare detailed response about excluded and eligible items
        $excludedItems = [];
        $eligibleItems = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['id']);
            $isExcluded = in_array($item['id'], $calculation['excluded_product_ids']) ||
                (isset($product->brand_id) && in_array($product->brand_id, $calculation['excluded_brand_ids']));
            $product = Product::find($item['id']);
            $itemData = [
                'id' => $item['id'],
                'name' => $product->name?? 'Product',
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total' => $item['price'] * $item['quantity']
            ];

            if ($isExcluded) {
                $excludedItems[] = $itemData;
            } else {
                $eligibleItems[] = $itemData;
            }
        }

        return response()->json([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $calculation['discount'],
            'calculation' => array_merge($calculation, [
                'excluded_items' => $excludedItems,
                'eligible_items' => $eligibleItems
            ])
        ]);
    }

    /**
     * Validate coupon for cart for guests
     */
    public function validateForCartGuest(Request $request): JsonResponse
    {

        $request->validate([
            'coupon_code' => 'required|string',
            'items' => 'required|array',
            'items.*.id' => 'required',
            'items.*.brand_id' => 'nullable',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.name' => 'sometimes|string',
        ]);
//        dd(Auth::user());
        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon not found']);
        }

        $user = auth()->user();
        $couponService = app(CouponService::class);

        // Validate coupon
        $validation = $couponService->validate($coupon, $user, $request->items);

        if (!$validation['valid']) {
            return response()->json($validation);
        }

        // Calculate discount for the cart
        $calculation = $couponService->calculateDiscount($coupon, $request->items);

        // Prepare detailed response about excluded and eligible items
        $excludedItems = [];
        $eligibleItems = [];

        foreach ($request->items as $item) {
            $isExcluded = in_array($item['id'], $calculation['excluded_product_ids']) ||
                (isset($item['brand_id']) && in_array($item['brand_id'], $calculation['excluded_brand_ids']));
            $product = Product::find($item['id']);
            $itemData = [
                'id' => $item['id'],
                'name' => $product->name?? 'Product',
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'total' => $item['price'] * $item['quantity']
            ];

            if ($isExcluded) {
                $excludedItems[] = $itemData;
            } else {
                $eligibleItems[] = $itemData;
            }
        }

        return response()->json([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $calculation['discount'],
            'calculation' => array_merge($calculation, [
                'excluded_items' => $excludedItems,
                'eligible_items' => $eligibleItems
            ])
        ]);
    }

}
