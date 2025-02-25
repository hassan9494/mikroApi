<?php


namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Modules\Shop\Emails\SendOrderDetailsEmail;
use Modules\Shop\Http\Resources\OrderResource;
use Modules\Shop\Repositories\Coupon\CouponRepository;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class OrderController extends Controller
{

    use ApiResponser;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $repository;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $products;

    /**
     * @var CouponRepository
     */
    private CouponRepository $coupons;

    /**
     * OrderController constructor.
     * @param OrderRepositoryInterface $repository
     * @param ProductRepositoryInterface $products
     * @param CouponRepository $coupons
     */
    public function __construct(
        OrderRepositoryInterface $repository,
        ProductRepositoryInterface $products,
        CouponRepository $coupons
    )
    {
        $this->repository = $repository;
        $this->products = $products;
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $orders = $this->repository->user(\Auth::user()->id);
        return OrderResource::collection($orders);
    }

    /**
     * @param Request $request
     * @return OrderResource
     */
    public function user(Request $request): OrderResource
    {

        $data = $request->validate([
            'products.*.id' => 'exists:products',
            'products.*.quantity' => 'numeric|min:1',
            'notes' => 'nullable|max:500',
            'coupon_id' => 'nullable|exists:coupons,id',
        ]);
        $data['user_id'] = Auth::user()->id;
        if (!Auth::user()->primaryAddress)
        {
            abort(400, 'Please the delivery address.');
        }
        $user=Auth::user();
        $order = $this->repository->makeByUser($data, Auth::user()->primaryAddress,$user);
        $details = [
            'subject' => 'Your Microelectron Order has been received',
        ];
//        Mail::to($user->email)->send(new SendOrderDetailsEmail($details,$order));
        return new OrderResource($order);
    }


    /**
     * @param Request $request
     * @return OrderResource
     */
    public function guest(Request $request): OrderResource
    {
        $data = $request->validate([
            'customer.name' => 'required|max:100',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'required|max:20',
            'city_id' => 'required|exists:cities,id',
            'shipping.address' => 'nullable|max:100',
            'notes' => 'nullable|max:500',
            'products' => 'required|min:1',
            'products.*.id' => 'exists:products',
            'products.*.quantity' => 'numeric|min:1',
            'coupon_id' => 'nullable|exists:coupons,id',
        ]);
        $order = $this->repository->makeByGuest($data);
        $details = [
            'subject' => 'Your Microelectron Order has been received',
        ];
//        Mail::to($request->customer['email'])->send(new SendOrderDetailsEmail($details,$order));
        return new OrderResource($order);
    }

}
