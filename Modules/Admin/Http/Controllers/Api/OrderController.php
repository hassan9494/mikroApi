<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Jobs\ProcessOrderToFatora;
use App\Traits\Datatable;
use Carbon\Carbon;
use DOMDocument;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\OrderForReturnResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Services\UblInvoiceService;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\OrderHistory;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\Transaction;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use function Ramsey\Uuid\uuid;

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


    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $conditions = json_decode(request('conditions'), true) ?? [];
        $search = request('search');
        $order = json_decode(request('order'), true);
        $page = request('page', 0);
        $limit = request('limit', 10);

        //  $query = Order::query()->with('histories.user');
        $query = Order::query();

        // Apply search conditions
        if (!empty($search)) {
//            dd($search);
            $query->where(function($q) use ($search) {
                $q->where('id', '=', $search)
                    ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%$search%"])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.name"))) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.email"))) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.phone"))) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhere('tax_number', 'LIKE', "%$search%")
                    ->orWhereRaw('LOWER(status) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(shipping, "$.status"))) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhere('total', 'LIKE', "%$search%");
            });
        }

        // Handle different condition formats
        if (!empty($conditions)) {
            // If conditions is an associative array (object format)
            if (isset($conditions['status']) || isset($conditions['options->taxed'])) {

                foreach ($conditions as $key => $value) {
                    if (str_contains($key, '->')) {
                        $query->where("$key", $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            }
            // If conditions is an array of condition objects
            else if (is_array($conditions) && isset($conditions[0]['col'])) {

                foreach ($conditions as $condition) {
                    if (isset($condition['col']) && isset($condition['op']) && isset($condition['val'])) {
                        $column = $condition['col'];
                        $operator = $condition['op'];
                        $value = $condition['val'];

                        // Handle IN operator
                        if ($operator === 'IN') {
                            if (str_contains($column, '->')) {
                                $query->whereIn("$column", $value);
                            } else {
                                $query->whereIn($column, $value);
                            }
                        } else {
                            if (str_contains($column, '->')) {
                                $query->where("$column", $operator, $value);
                            } else {
                                $query->where($column, $operator, $value);
                            }
                        }
                    }
                }
            }
            else{
                foreach ($conditions as $key => $value) {
                    if ($key == 'not_admin' && $value == 1){
                        $query->where(function ($q) {
                            $q->where('status', '!=', 'completed')
                                ->orWhere('options->taxed', true);
                        });
                    }else{
                        $query->where($key, $value);
                    }

                }
            }
        }

        // Apply ordering
        if (!empty($order) && isset($order['column']) && isset($order['dir'])) {
            $query->orderBy($order['column'], $order['dir']);
        }

        // Get paginated results
        $total = $query->count();
        $items = $query->skip($page * $limit)->take($limit)->get();

        return response()->json([
            'data' => [
                'items' => OrderResource::collection($items),
                'total' => $total
            ]
        ]);
    }


    /**
     * @param $id
     * @return OrderResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        $model->load('histories.user');
        $model->load('transactions');
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
    public function autocomplete(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocomplete($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'tax_number' => $model->tax_number,
                'products' => $model->products,
            ];
        }
        return $this->success(OrderForReturnResource::collection($models));
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
        $order->recordHistory('created', null, 'Order created through admin');

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
        $oldData = $order->toArray();
        $data = $this->validate();
        if ($order->status != 'COMPLETED'){
//            if (!$order->options->price_offer){


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

        }else{
            $order = $this->repository->saveOrder($id, $data);
            $order->syncMedia($data['attachments'] ?? []);
        }
        return $this->success();
    }

    public function updateWithStatus($id): JsonResponse
    {
        $order = $this->repository->findOrFail($id);
        $oldStatus = $order->status;
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
                if (isset($data['products'])){
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

            }
//            return \response()->json($data);
            if ($data['shipping']['status'] == null) {
                $data['shipping']['status'] = "WAITING";
            }
            if (request()->get('status') == 'COMPLETED'){
                $data['completed_by'] = auth()->id();

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
        if (request()->get('status') && $oldStatus != request()->get('status')) {
            $order->recordStatusChange($oldStatus, request()->get('status'));
        }
        if (request()->get('status') == 'COMPLETED'){
            $old_transaction = Transaction::where('order_id',$order->id)->first();
            if ($old_transaction){
                $old_transaction->update([
                    'note' => '',
                    'type' => 'deposit',
                    'amount' => request()->get('amount'),
                    'commission' => request()->get('commission'),
                    'shipping' => request()->get('shipping_amount'),
                    'total_amount' => request()->get('amount') - request()->get('shipping_amount') - request()->get('commission'),
                    'payment_method_id' => request()->get('payment_method')
                ]);
            }else{
                $transaction = $order->transactions()->create([
                    'transaction_id' => Str::uuid(),
                    'note' => '',
                    'type' => 'deposit',
                    'amount' => request()->get('amount'),
                    'commission' => request()->get('commission'),
                    'shipping' => request()->get('shipping_amount'),
                    'total_amount' => request()->get('amount') - request()->get('shipping_amount') - request()->get('commission'),
                    'payment_method_id' => request()->get('payment_method')
                ]);
            }

        }
        return $this->success();
    }
    private function getChangedFields($oldData, $newData)
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (!array_key_exists($key, $oldData) || $oldData[$key] != $value) {
                $changes[$key] = $value;
            }
        }
        return $changes;
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
            'tax_exempt_id' => 'nullable|exists:tax_exempts,id',
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
            'shipping.city' => 'nullable|string|max:255',
            'options.taxed' => 'required|boolean',
            'options.tax_exempt' => 'required|boolean',
            'options.dept' => 'required|boolean',
            'options.price_offer' => 'required|boolean',
            'options.pricing' => 'nullable|string',
            'options.tax_zero' => 'nullable|boolean',
            'pending' => 'nullable|boolean',

            'coupon_id' => 'nullable|exists:coupons,id',
            'discount' => 'required|numeric',
            'discount_percentage' => 'required|numeric',
            'notes' => 'nullable|max:500',
            'invoice_notes' => 'nullable|max:500',

            'products.*.id' => 'exists:products,id',
//            'products.*.color_id' => 'nullable|exists:product_variants,id',
//            'products.*.is_color' => 'nullable|boolean',
            'products.*.price' => 'required|numeric',
            'products.*.quantity' => 'required|numeric',
            'products.*.number' => 'required|numeric',
            'products.*.discount' => 'required|numeric',

            'extra_items' => 'nullable|array',

            'attachments' => 'nullable|array',


            'amount' => 'nullable',
            'commission' => 'nullable',
            'payment_method' => 'nullable',
            'shipping_amount' => 'nullable',
        ]);
    }

    public function orderToFatoraSystem( $id)
    {
        set_time_limit(300);
        $order = Order::find($id);
        $service = new UblInvoiceService();
        $orderToFatora = $this->calcOrderFatora($order);

        // 1. Generate XML
        $xml = $service->generate($orderToFatora);
//        return config('app_phase');
        if (config('jo_fotara.app_phase') == 'testing'){
            return response()->json([
                'status' => 'fail',
                'invoice_id' => $xml,
                'phase' => config('jo_fotara.app_phase'),
                'user-id' => auth()->id()
            ],500);
        }
        $payload = $service->prepareForSubmission($xml);

        $response = Http::withHeaders([
            'Client-Id' => config('jo_fotara.client_id'),
            'Secret-Key' => config('jo_fotara.secret_key'),
            'Content-Type' => 'application/json',
        ])->post(config('jo_fotara.api_url').'/core/invoices/', $payload);

        // 5. Handle Response
        if ($response->successful()) {

            $responseData = $response->json();
//            Log::info(['JoFotara Response json'=> $responseData]);
            $oldOrder = Order::find($id);

            $oldOrder->update([
                'qr_code' => $responseData['EINV_QR'],
                'fatora_status' => $responseData['EINV_STATUS'],
                'is_migrated' => true,
                'migrated_at' => now(),
                'migrated_by' => auth()->id()
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
        Log::error(['JoFotara Response failed'=> $response->body()]);
        $oldOrder = Order::find($id);
        $responseData = $response->body();
        $oldOrder->update([
            'fatora_status' => $responseData['EINV_RESULTS']['EINV_STATUS'] ?? 'failed',
            'migrate_error' => $responseData['EINV_RESULTS']['ERRORS'] ?? $this->mapErrorCode($errorCode),
            'is_migrated' => false,
        ]);
        return response()->json([
            'status' => 'error',
            'code' => $errorCode,
            'message' => $responseData['EINV_RESULTS']['ERRORS'] ?? $this->mapErrorCode($errorCode),
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
            $discount += number_format($product->pivot->discount / (1+$taxValue),9, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $discount += number_format($product->discount / (1+$taxValue),9, '.', '');
            }
        }

        return $discount;
    }

    private function calcFinalTax($order,$taxValue)
    {
        $tax = 0;
        foreach ($order->products as $product){
            $tax += number_format((($product->pivot->quantity *number_format(($product->pivot->price /(1+$taxValue)), 9, '.', '') ) - (number_format(($product->pivot->discount /(1+$taxValue)),9, '.', ''))) * $taxValue,9, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $tax += number_format((($product->quantity *($product->price /(1+$taxValue)) ) - (number_format(($product->discount /(1+$taxValue)),9, '.', ''))) * $taxValue,9, '.', '');
            }
        }

        return $tax;
    }

    private function calcFinalTotal($order,$taxValue)
    {
        $total = 0;
        foreach ($order->products as $product){
            $total += number_format((number_format(($product->pivot->price / (1+$taxValue)), 9, '.', '') * $product->pivot->quantity), 9, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $total += number_format((number_format(($product->price / (1+$taxValue)), 9, '.', '') * $product->quantity), 9, '.', '');
            }
        }

        return $total;
    }

    public function migrateMultipleOrders()
    {
        // Use Carbon's parse or createFromFormat consistently
        $startDate = Carbon::createFromFormat('d-m-Y', '01-04-2025')->startOfDay();

        $orders = Order::where('status', 'COMPLETED')
            ->where('options->taxed', true)
            ->where('is_migrated', false)
            ->whereDate('taxed_at', '>=', $startDate)  // Use whereDate for date comparison
            ->get();

        $userId = auth()->id();

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No orders found for migration',
                'order_ids' => []
            ]);
        }

        foreach ($orders as $key=>$order) {
                ProcessOrderToFatora::dispatch($order, $userId);  // Consider using a specific queue


        }

        return response()->json([
            'status' => 'queued',
            'message' => $orders->count() . ' orders have been queued for migration',
            'order_ids' => $orders->pluck('id')
        ]);
    }

    public function print($id)
    {
        $order = $this->repository->findOrFail($id);

        // Record print action
        $order->recordPrintAction();

        return view('orders.print', compact('order'));
    }
    /**
     * Record print action for an order
     *
     * @param int $id
     * @return JsonResponse
     */
    /**
     * Record print action for an order
     *
     * @param int $id
     * @return JsonResponse
     */
    public function recordPrint($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->recordPrintAction();

            return response()->json([
                'success' => true,
                'message' => 'Print action recorded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record print action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record export action for an order
     *
     * @param int $id
     * @return JsonResponse
     */
    public function recordExport($id)
    {
        try {
            $order = Order::findOrFail($id);

            $user = auth()->user();
            OrderHistory::create([
                'order_id' => $order->id,
                'action' => 'exported',
                'notes' => 'Order was exported to Excel',
                'details' => json_encode([
                    'exported_by' => $user ? $user->name : 'Unknown',
                    'exported_at' => now()->toDateTimeString(),
                    'export_format' => 'Excel'
                ]),
                'user_id' => $user ? $user->id : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Export action recorded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record export action: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recordEditView($id)
    {
        try {
            $order = $this->repository->findOrFail($id);
            $user = auth()->user();

            // Record the view in order history
            $order->recordHistory('viewed_edit', null, 'Order edit page was viewed by ' . $user->name);

            return response()->json([
                'success' => true,
                'message' => 'Edit view recorded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record edit view: ' . $e->getMessage()
            ], 500);
        }
    }



}
