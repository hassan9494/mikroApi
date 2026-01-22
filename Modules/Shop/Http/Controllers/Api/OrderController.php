<?php


namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use AWS\CRT\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Modules\Shop\Emails\SendOrderDetailsEmail;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;
use Modules\Shop\Http\Resources\OrderResource;
use Modules\Shop\Repositories\Coupon\CouponRepository;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

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
//            'products.*.variant_id' => 'nullable|exists:product_variants,id',
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
        foreach ($data['products'] as $product){
            \Log::info('product :'.json_encode($product));
//            if ($product['variant_id']){
//               $variant = ProductVariant::find($product['variant_id']);
//               $prod = Product::find($variant->color_id);
//            }else{
//                $prod = Product::find($product['id']);
//            }
            if (isset($product['id'])){
                $prod = Product::find($product['id']);
                if ($prod->stock < $product['quantity']){
                    throw new BadRequestException($prod->name . ' has insufficient quantity');
                }
                if ($prod->options->kit == true){
                    $kits = $prod->kit()->get();
//                        return response()->json($kits);
                    foreach ($kits as $kit){
//                            return response()->json($kit->name);
                        if ($kit->pivot->quantity * $product['quantity'] > $kit->stock){
                            throw new BadRequestException($kit->name . ' Which is kit has insufficient quantity');
                        }
                    }
                }
            }else{
                \Log::error('product has error :'.json_encode($product));
            }

        }
//        dd($prod);
        $order = $this->repository->makeByUser($data, Auth::user()->primaryAddress,$user);
        $details = [
            'subject' => 'Your Microelectron Order has been received',
        ];
        Mail::to($user->email)->send(new SendOrderDetailsEmail($details,$order));

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
        foreach ($data['products'] as $product){
            $prod = Product::find($product['id']);
            if ($prod->stock < $product['quantity']){
                throw new BadRequestException($prod->name . ' has insufficient quantity');
            }
            if ($prod->options->kit == true){
                $kits = $prod->kit()->get();
//                        return response()->json($kits);
                foreach ($kits as $kit){
//                            return response()->json($kit->name);
                    if ($kit->pivot->quantity * $product['quantity'] > $kit->stock){
                        throw new BadRequestException($kit->name . ' Which is kit has insufficient quantity');
                    }
                }
            }
        }
        $order = $this->repository->makeByGuest($data);
        $details = [
            'subject' => 'Your Microelectron Order has been received',
        ];

        Mail::to($request->customer['email'])->send(new SendOrderDetailsEmail($details,$order));

        return new OrderResource($order);
    }

    /**
     * @param Request $request
     * @return OrderResource
     */
    public function employeeOrder(Request $request): OrderResource
    {
        $user = auth()->user();

        // Check if user is an employee (has roles other than just 'user')
        $roleNames = $user->roles->pluck('name')->toArray();
        $hasOnlyUserRole = count($roleNames) === 1 && in_array('user', $roleNames);

        if ($hasOnlyUserRole) {
            abort(403, 'This endpoint is for employees only');
        }

        $data = $request->validate([
            'customer.name' => 'required|max:100',
            'customer.email' => 'nullable|email|max:255', // Email is optional for employees
            'customer.phone' => 'required|max:20',
            'city_id' => 'required|exists:cities,id',
            'shipping.address' => 'nullable|max:100',
            'notes' => 'nullable|max:500',
            'products' => 'required|min:1',
            'products.*.id' => 'exists:products',
            'products.*.quantity' => 'numeric|min:1',
            'coupon_id' => 'nullable|exists:coupons,id',
        ]);

        // Fill empty email with employee's email or a placeholder
        if (empty($data['customer']['email'])) {
            $data['customer']['email'] = $user->email ?? 'employee-order@microelectron.com';
        }

        foreach ($data['products'] as $product){
            $prod = Product::find($product['id']);
            if ($prod->stock < $product['quantity']){
                throw new BadRequestException($prod->name . ' has insufficient quantity');
            }
            if ($prod->options->kit == true){
                $kits = $prod->kit()->get();
                foreach ($kits as $kit){
                    if ($kit->pivot->quantity * $product['quantity'] > $kit->stock){
                        throw new BadRequestException($kit->name . ' Which is kit has insufficient quantity');
                    }
                }
            }
        }

        $order = $this->repository->makeByEmployee($data, $user);

        // Only send email if it's provided and valid
        if (!empty($data['customer']['email']) && filter_var($data['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            $details = [
                'subject' => 'Your Microelectron Order has been received',
            ];
            Mail::to($data['customer']['email'])->send(new SendOrderDetailsEmail($details,$order));
        }

        return new OrderResource($order);
    }


}
