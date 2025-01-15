<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class OrderController extends ApiAdminController
{

    /**
     * OrderController constructor.
     * @param OrderRepositoryInterface $repository
     */
    public function __construct(OrderRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
//    /**
//     * @return JsonResponse
//     */
//    public function datatable(): JsonResponse
//    {
//        return Datatable::make($this->repository->model())
//            ->search('id', 'customer->name', 'customer->phone')
//            ->resource(OrderResource::class)
//            ->json();
//    }

    /**
     * @param $id
     * @return OrderResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return new OrderResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function sales(): JsonResponse
    {
        $from = request('from');
        $to = request('to');
        $id = json_decode(request('conditions'))[0]->id;
        $whereHas['Products'] =  function ($q) use ($id) {
            if ($id) $q->where('products.id',  $id);
        };
        return Datatable::make($this->repository->model())
            ->with(['products'])
            ->whereHas($whereHas)
            ->search('id', 'name', 'sku')
            ->resource(OrderResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();
        if ($data['shipping']['status'] == null) {
            $data['shipping']['status'] = "WAITING";
        }

        $order = $this->repository->make($data);

        if (!\Arr::get($data, 'options.price_offer', false)) {
            // Mark as processing
            $this->repository->status($order->id, OrderStatus::PROCESSING()->value);
        } else {
            $this->repository->status($order->id, OrderStatus::PENDING()->value);
        }

        $order->syncMedia($data['attachments'] ?? []);

        return $this->success([
            'id' => $order->id
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $order = $this->repository->findOrFail($id);
        if ($order->status != 'COMPLETED'){
            $data = $this->validate();
            if ($order->status == 'PENDING' &&  request()->get('status') != 'PENDING'){
                foreach ($data['products'] as $product){
                    $prod = Product::find($product['id']);
                    if ($prod->stock < $product['quantity']){
                        throw new BadRequestException($prod->name . ' has insufficient quantity');
                    }
                }
            }
//            return \response()->json($data);
            if ($data['shipping']['status'] == null) {
                $data['shipping']['status'] = "WAITING";
            }
            $order = $this->repository->saveOrder($id, $data);
            $order->syncMedia($data['attachments'] ?? []);
            $this->repository->status(
                $id, request()->get('status')
            );

        }else{
            $this->repository->status(
                $id, request()->get('status')
            );
        }
        return $this->success();
    }

    /**
     * @param $id
     */
    public function status($id)
    {
        $this->repository->status(
            $id, request()->get('status')
        );
    }


    /**
     * @param $id
     */
    public function shippingStatus($id)
    {
        $this->repository->update(
            $id, request()->only('shipping_status')
        );
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return [
            'id', 'customer->name', 'customer->email', 'customer->phone','tax_number','status','shipping->status','total'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'user_id' => 'nullable|exists:users,id',

            'customer.name' => 'required|max:255',
            'customer.phone' => 'required|max:255',
            'customer.email' => 'nullable|email|max:255',

            'city_id' => 'nullable',
            'shipping_provider_id' => 'nullable',
            'shipping.address' => 'nullable',
            'shipping.cost' => 'nullable',
            'shipping.status' => 'nullable',
            'shipping.free' => 'nullable|boolean',

            'options.taxed' => 'required|boolean',
            'options.tax_exempt' => 'required|boolean',
            'options.dept' => 'required|boolean',
            'options.price_offer' => 'required|boolean',
            'options.pricing' => 'nullable|string',
            'options.tax_zero' => 'nullable|boolean',

            'coupon_id' => 'nullable|exists:coupons,id',
            'discount' => 'required|numeric',
            'notes' => 'nullable|max:500',
            'invoice_notes' => 'nullable|max:500',

            'products.*.id' => 'exists:products,id',
            'products.*.price' => 'required|numeric',
            'products.*.quantity' => 'required|numeric',

            'extra_items' => 'nullable|array',

            'attachments' => 'nullable|array',
        ]);
    }

}
