<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use DOMDocument;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Services\UblInvoiceService;
use Modules\Shop\Entities\Order;
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
//            if (!$order->options->price_offer){

                $data = $this->validate();
//                if ($order->status != 'PENDING'){
//                    foreach ($data['products'] as $product){
//                        $prod = Product::find($product['id']);
//                        if ($prod->stock < $product['quantity']){
//                            throw new BadRequestException($prod->name . ' has insufficient quantity');
//                        }
//                    }
//                }
//            return \response()->json($data);
                if ($data['shipping']['status'] == null) {
                    $data['shipping']['status'] = "WAITING";
                }
                $order = $this->repository->saveOrder($id, $data);
                $order->syncMedia($data['attachments'] ?? []);
//            }

        }
        return $this->success();
    }

    public function updateWithStatus($id): JsonResponse
    {
        $order = $this->repository->findOrFail($id);
        if ($order->status != 'COMPLETED'){
            $data = $this->validate();
            if ($order->status == 'PROCESSING' && request()->get('status') == 'PENDING'){
                $employee = Auth::user();
//                return response()->json(!$employee->hasRole('super') && !$employee->hasRole('admin') && !$employee->hasRole('Manager'));
                if (!$employee->hasRole('super') && !$employee->hasRole('admin') && $order->options->taxed){
                    throw new BadRequestException('You can\'t update the status please contact admin');
                }
            }

            if ($order->status == 'PENDING' &&  request()->get('status') != 'PENDING'){
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
            if (request()->get('status') != null){
                $this->repository->status(
                    $id, request()->get('status')
                );
            }

        }
        return $this->success();
    }

    public function migrateOrder($id): JsonResponse
    {

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
            'cashier_id' => 'nullable|exists:users,id',

            'customer.name' => 'required|max:255',
            'customer.phone' => 'required|max:14|min:9',
            'customer.email' => 'nullable|email|max:255',
            'customer_identity_number' => 'nullable',
            'identity_number_type' => 'nullable|max:3',

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
            'discount_percentage' => 'required|numeric',
            'notes' => 'nullable|max:500',
            'invoice_notes' => 'nullable|max:500',

            'products.*.id' => 'exists:products,id',
            'products.*.price' => 'required|numeric',
            'products.*.quantity' => 'required|numeric',
            'products.*.number' => 'required|numeric',
            'products.*.discount' => 'required|numeric',

            'extra_items' => 'nullable|array',

            'attachments' => 'nullable|array',
        ]);
    }

    public function orderToFatoraSystem( $id)
    {
        $order = Order::find($id);
        $service = new UblInvoiceService();
        $orderToFatora = $this->calcOrderFatora($order);

        // 1. Generate XML
        $xml = $service->generate($orderToFatora);
//        return response()->json([
//            'status' => 'success',
//            'invoice_id' => $xml
//        ]);
        $payload = $service->prepareForSubmission($xml);


        Log::info('XML before submission:', [$payload]);

//        $jsonPayload = [
//            'invoice' => base64_encode($minfiedXml)
//        ];
//        $filePath = storage_path('app/invoice.xml'); // Choose a path
//        File::put($filePath, $xml);


//        $validation = $service->validateXml($xml);
//        if (!$validation['valid']) {
//            return response()->json([
//                'status' => 'error',
//                'message' => 'XML validation failed',
//                'errors' => $validation['errors']
//            ], 422);
//        }





        $response = Http::withHeaders([
            'Client-Id' => config('jo_fotara.client_id'),
            'Secret-Key' => config('jo_fotara.secret_key'),
            'Content-Type' => 'application/json',
        ])->post(config('jo_fotara.api_url').'/core/invoices/', $payload);

        // 4. Submit to JoFotara
//        Log::info(['JoFotara Response'=> $response]);

        // 5. Handle Response
        if ($response->successful()) {

            $responseData = $response->json();
            Log::info(['JoFotara Response json'=> $responseData]);
//            Log::info(['JoFotara Response qr'=> $responseData['EINV_QR']]);
//            Log::info(['JoFotara Response qr'=> $responseData['EINV_STATUS']]);
            $oldOrder = Order::find($id);

            $oldOrder->update([
                'qr_code' => $responseData['EINV_QR'],
                'fatora_status' => $responseData['EINV_STATUS'],
                'is_migrated' => true
            ]);
            return response()->json([
                'status' => 'success',
                'invoice_id' => $xml,
                'qr_code_url' => $responseData['EINV_QR'],
                'fatora_status' => $responseData['EINV_STATUS']
            ]);
        }

        $errorCode = $response->json('errorCode');
        $errorMessage = $this->mapErrorCode($errorCode);
        Log::error(['JoFotara Response'=> $response->reason()]);
        Log::error(['JoFotara Response failed'=> $response->body()]);
        return response()->json([
            'status' => 'error',
            'code' => $errorCode,
            'message' => $errorMessage,
            'details' => $response->json()
        ], 400);
    }

    private function mapErrorCode($code)
    {
        // Map error codes from documentation
        $errors = [
            'E001' => 'Invalid client credentials',
            'E002' => 'Invalid XML structure',
            'E003' => 'Duplicate invoice submission',
            // Add more codes from documentation
        ];

        return $errors[$code] ?? 'Unknown error';
    }

    private function calcOrderFatora(Order $order)
    {

        $is_taxed = $order->options->taxed;
        $is_exempt = $order->options->tax_exempt;
        $tax_zero = $order->options->tax_zero;
        $taxChar = $this->tax($is_taxed,$is_exempt,$tax_zero);
        $taxValue = ($taxChar == 'S') ? 0.16 : 0;
        $totalTax = ($order->total / (1 + $taxValue)) * $taxValue;
        $totalBeforDiscount =$order->subtotal - ($order->subtotal / (1 + $taxValue)) * $taxValue;
        $totalAfterDiscountAndTax = $order->total;
        $fixedOrder = $order;
        $fixedOrder->tax_char = $taxChar;
        $fixedOrder->tax_value = $taxValue;
        $fixedOrder->totalTax = $totalTax;
        $fixedOrder->totalBeforDiscount = $totalBeforDiscount;
        $fixedOrder->totalAfterDiscountAndTax = $totalAfterDiscountAndTax;

        $fixedOrder->final_discount = $this->calcFinalDiscount($order,$taxValue);
        $fixedOrder->final_tax = $this->calcFinalTax($order,$taxValue);
        $fixedOrder->final_total = $this->calcFinalTotal($order,$taxValue);

        return $fixedOrder;



    }

    private function tax($is_taxed,$is_exempt,$tax_zero)
    {
        if ($is_taxed && !$is_exempt && !$tax_zero){
            return 'S';
        }elseif ($is_taxed && $is_exempt && !$tax_zero){
            return 'Z';
        }elseif ($is_taxed && $is_exempt && $tax_zero){
            return 'O';
        }else{
            return null;
        }

    }

    private function calcFinalDiscount($order,$taxValue)
    {
        $discount = 0;
        foreach ($order->products as $product){
            $discount += number_format($product->pivot->discount / (1+$taxValue),3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $discount += number_format($product->discount / (1+$taxValue),3, '.', '');
            }
        }

        return $discount;
    }

    private function calcFinalTax($order,$taxValue)
    {
        $tax = 0;
        foreach ($order->products as $product){
            $tax += number_format((($product->pivot->quantity *number_format(($product->pivot->price /(1+$taxValue)), 3, '.', '') ) - (number_format(($product->pivot->discount /(1+$taxValue)),3, '.', ''))) * $taxValue,3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $tax += number_format((($product->quantity *($product->price /(1+$taxValue)) ) - (number_format(($product->discount /(1+$taxValue)),3, '.', ''))) * $taxValue,3, '.', '');
            }
        }

        return $tax;
    }

    private function calcFinalTotal($order,$taxValue)
    {
        $total = 0;
        foreach ($order->products as $product){
            $total += number_format((number_format(($product->pivot->price / (1+$taxValue)), 3, '.', '') * $product->pivot->quantity), 3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $total += number_format((number_format(($product->price / (1+$taxValue)), 3, '.', '') * $product->quantity), 3, '.', '');
            }
        }

        return $total;
    }

}
