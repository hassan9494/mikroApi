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
use Modules\Admin\Http\Resources\ReturnOrderResource;
use Modules\Admin\Http\Services\UblInvoiceService;
use Modules\Shop\Entities\ReturnOrder;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\ReturnOrder\ReturnOrderRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class ReturnOrderController extends ApiAdminController
{

    /**
     * ReturnOrderController constructor.
     * @param ReturnOrderRepositoryInterface $repository
     */
    public function __construct(ReturnOrderRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
//    /**
//     * @return JsonResponse
//     */
    public function datatable1(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id','number', 'order_id')
            ->resource(ReturnOrderResource::class)
            ->json();
    }

    public function datatable(): JsonResponse
    {
        $conditions = json_decode(request('conditions'), true) ?? [];
        $search = request('search');
        $order = json_decode(request('order'), true);
        $page = request('page', 0);
        $limit = request('limit', 10);

        //  $query = Order::query()->with('histories.user');
        $query = ReturnOrder::query();

        // Apply search conditions
        if (!empty($search)) {
//            dd($search);
            $query->where(function ($q) use ($search) {
                $q->where('id', '=', $search)
                    ->orWhere('number','LIKE',"%$search%")
                    ->orWhereHas('order',function ($q) use ($search){
                        $q->whereRaw('CAST(id AS CHAR) LIKE ?', ["%$search%"])
                            ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%$search%"])
                            ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.name"))) LIKE ?', ['%' . strtolower($search) . '%'])
                            ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.email"))) LIKE ?', ['%' . strtolower($search) . '%'])
                            ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(customer, "$.phone"))) LIKE ?', ['%' . strtolower($search) . '%'])
                            ->orWhere('tax_number', 'LIKE', "%$search%")
                            ->orWhereRaw('LOWER(status) LIKE ?', ['%' . strtolower($search) . '%'])
                            ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(shipping, "$.status"))) LIKE ?', ['%' . strtolower($search) . '%'])
                            ->orWhere('total', 'LIKE', "%$search%");
                    });

            });
        }

        // Handle different condition formats
        if (!empty($conditions)) {

            // If conditions is an associative array (object format)
            if (isset($conditions['status'])) {

                foreach ($conditions as $key => $value) {
                    if (str_contains($key, '->')) {
                        $query->where("$key", $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            } // If conditions is an array of condition objects
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
                    elseif (isset($condition['col']) && isset($condition['val'])) {
                        if ($condition['col'] == 'taxType'){
                            switch ($condition['val']){
                                case 'taxable' :
                                    $query->whereHas('order',function ($q){
                                        $q->where('options->taxed',true)
                                            ->where('options->tax_exempt',false)
                                            ->where('options->tax_zero',false);
                                    });
                                    break;
                                case 'exempt' :
                                    $query->whereHas('order',function ($q){
                                        $q->where('options->taxed',true)
                                            ->where('options->tax_exempt',true)
                                            ->where('options->tax_zero',false);
                                    });
                                    break;
                                case 'zero_rate' :
                                    $query->whereHas('order',function ($q){
                                        $q->where('options->taxed',true)
                                            ->where('options->tax_exempt',true)
                                            ->where('options->tax_zero',true);
                                    });
                                    break;
                            }
                        }else{
                            $column = $condition['col'];
                            $value = $condition['val'];

                            if (str_contains($column, '->')) {
                                $query->where("$column", $value);
                            } else {
                                $query->where($column, $value);
                            }
                        }

                    }
                }
            } else {
                foreach ($conditions as $key => $value) {
                    if ($key == 'not_admin' && $value == 1) {
                        $query->where(function ($q) {
                            $q->where('status', '!=', 'completed')
                                ->orWhere('options->taxed', true);
                        });
                    } else {
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
                'items' => ReturnOrderResource::collection($items),
                'total' => $total
            ]
        ]);
    }

    /**
     * @param $id
     * @return ReturnOrderResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return new ReturnOrderResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function sales(): JsonResponse
    {
        $from = request('from');
        $to = request('to');
        $id = json_decode(request('conditions'))[0]->id;
        $whereHas['Products'] = function ($q) use ($id) {
            if ($id) $q->where('products.id', $id);
        };
        return Datatable::make($this->repository->model())
            ->with(['products'])
            ->whereHas($whereHas)
            ->search('id', 'name', 'sku')
            ->resource(ReturnOrderResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();


        $order = $this->repository->make($data);


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
        $data = $this->validate();
        $order = $this->repository->saveOrder($id, $data);
        $order->syncMedia($data['attachments'] ?? []);
        return $this->success();
    }

    public function updateWithStatus($id): JsonResponse
    {
        $order = $this->repository->findOrFail($id);

        if ($order->status != 'COMPLETED') {
            $data = $this->validate();

            if (request()->get('status') == 'COMPLETED') {
                $data['completed_by'] = auth()->id();
            }

            $order = $this->repository->saveOrder($id, $data);
            $order->syncMedia($data['attachments'] ?? []);

            // Update status with products for stock handling
            $this->repository->status(
                $id,
                request()->get('status'),
                request()->get('products')
            );

        } else {
            if (request()->get('status') != null) {
                // Handle status change for completed orders
                $this->repository->status(
                    $id,
                    request()->get('status'),
                    request()->get('products')
                );
            }
        }
        if (request()->get('status') == 'COMPLETED') {
//            $old_transaction = Transaction::where('order_id', $order->id)->first();
//            if ($old_transaction){
//                $old_transaction->update([
//                    'note' => '',
//                    'type' => 'deposit',
//                    'amount' => request()->get('amount'),
//                    'commission' => request()->get('commission'),
//                    'shipping' => request()->get('shipping_amount'),
//                    'total_amount' => request()->get('amount') - request()->get('shipping_amount') - request()->get('commission'),
//                    'payment_method_id' => request()->get('payment_method')
//                ]);
//            }else{
            if (request()->get('amount') > 0){
                $transaction = $order->transactions()->create([
                    'transaction_id' => Str::uuid(),
                    'note' => '',
                    'return_order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => request()->get('amount'),
                    'commission' => request()->get('commission'),
                    'shipping' => request()->get('shipping_amount'),
                    'total_amount' => request()->get('amount') - request()->get('shipping_amount') - request()->get('commission'),
                    'payment_method_id' => request()->get('payment_method'),
                    'created_by' => auth()->id()
                ]);
            }

//        }
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
            'id', 'customer->name', 'customer->email', 'customer->phone', 'tax_number', 'status', 'shipping->status', 'total'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'order_id' => 'nullable|exists:orders,id',
            'discount' => 'required|numeric',
            'notes' => 'required|max:500',
            'status' => 'required|string',

            'products.*.id' => 'exists:products,id',
            'products.*.price' => 'required|numeric',
            'products.*.returned_quantity' => 'required|numeric',
            'products.*.quantity' => 'required|numeric',
            'products.*.discount' => 'required|numeric',
            'products.*.main_discount' => 'required|numeric',

            'extra_items' => 'nullable|array',

            'attachments' => 'nullable|array',
        ]);
    }

    public function orderToFatoraSystem($id)
    {
        $order = ReturnOrder::find($id);
        $service = new UblInvoiceService();
        $orderToFatora = $this->calcOrderFatora($order);

        // 1. Generate XML
        $xml = $service->generateForReturn($orderToFatora);
        if (config('jo_fotara.app_phase') == 'testing'){
            return response()->json([
                'status' => 'fail',
                'invoice_id' => $xml,
                'phase' => config('jo_fotara.app_phase'),
                'orderToFatora' => $orderToFatora,
                'user-id' => auth()->id()
            ],500);
        }
//        return response()->json([
//            'status' => 'success',
//            'invoice_id' => $xml,
//            'orderToFatora' => $orderToFatora,
//            'user-id' => auth()->id()
//        ]);
        $payload = $service->prepareForSubmission($xml);
        $response = Http::withHeaders([
            'Client-Id' => config('jo_fotara.client_id'),
            'Secret-Key' => config('jo_fotara.secret_key'),
            'Content-Type' => 'application/json',
        ])->post(config('jo_fotara.api_url') . '/core/invoices/', $payload);

        // 5. Handle Response
        if ($response->successful()) {

            $responseData = $response->json();
//            Log::info(['JoFotara Response json'=> $responseData]);
            $oldOrder = ReturnOrder::find($id);

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
        Log::error(['JoFotara Response failed' => $response->body()]);
        $oldOrder = ReturnOrder::find($id);
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

    private function calcOrderFatora(ReturnOrder $order)
    {

        $is_taxed = $order->order->options->taxed;
        $is_exempt = $order->order->options->tax_exempt;
        $tax_zero = $order->order->options->tax_zero;
        $taxChar = $this->tax($is_taxed, $is_exempt, $tax_zero);
        $taxValue = ($taxChar == 'S') ? 0.16 : 0;
        $totalTax = ($order->total / (1 + $taxValue)) * $taxValue;
        $totalBeforDiscount = $order->subtotal - ($order->subtotal / (1 + $taxValue)) * $taxValue;
        $totalAfterDiscountAndTax = $order->total;
        $fixedOrder = $order;
        $fixedOrder->tax_char = $taxChar;
        $fixedOrder->tax_value = $taxValue;
        $fixedOrder->totalTax = $totalTax;
        $fixedOrder->totalBeforDiscount = $totalBeforDiscount;
        $fixedOrder->totalAfterDiscountAndTax = $totalAfterDiscountAndTax;

        $fixedOrder->final_discount = $this->calcFinalDiscount($order, $taxValue);
        $fixedOrder->final_tax = $this->calcFinalTax($order, $taxValue);
        $fixedOrder->final_total = $this->calcFinalTotal($order, $taxValue);
        $fixedOrder->old_final_total = $this->calcOldFinalTotal($order->order, $taxValue);

        return $fixedOrder;


    }

    private function tax($is_taxed, $is_exempt, $tax_zero)
    {
        if ($is_taxed && !$is_exempt && !$tax_zero) {
            return 'S';
        } elseif ($is_taxed && $is_exempt && !$tax_zero) {
            return 'Z';
        } elseif ($is_taxed && $is_exempt && $tax_zero) {
            return 'O';
        } else {
            return null;
        }

    }

    private function calcFinalDiscount($order, $taxValue)
    {
        $discount = 0;
        foreach ($order->products as $product) {
            if ($product->pivot->returned_quantity > 0) {
                $discount += number_format($product->pivot->discount / (1 + $taxValue), 9, '.', '');
            }

        }
        if ($order->extra_items != null && count($order->extra_items) > 0) {
            foreach ($order->extra_items as $product) {
                if ($product->returned_quantity > 0) {
                    $discount += number_format($product->discount / (1 + $taxValue), 9, '.', '');
                }
            }
        }

        return $discount;
    }

    private function calcFinalTax($order, $taxValue)
    {
        $tax = 0;
        foreach ($order->products as $product) {
            if ($product->pivot->returned_quantity > 0) {
                $tax += number_format((($product->pivot->returned_quantity * number_format(($product->pivot->price / (1 + $taxValue)), 9, '.', '')) - (number_format(($product->pivot->discount / (1 + $taxValue)), 9, '.', ''))) * $taxValue, 9, '.', '');
            }
        }
        if ($order->extra_items != null && count($order->extra_items) > 0) {
            foreach ($order->extra_items as $product) {
                if ($product->returned_quantity > 0) {
                    $tax += number_format((($product->returned_quantity * ($product->price / (1 + $taxValue))) - (number_format(($product->discount / (1 + $taxValue)), 9, '.', ''))) * $taxValue, 9, '.', '');
                }
            }
        }

        return $tax;
    }

    private function calcFinalTotal($order, $taxValue)
    {
        $total = 0;
        foreach ($order->products as $product) {
            if ($product->pivot->returned_quantity > 0) {
                $total += number_format((number_format(($product->pivot->price / (1 + $taxValue)), 9, '.', '') * $product->pivot->returned_quantity), 9, '.', '');
            }
        }
        if ($order->extra_items != null && count($order->extra_items) > 0) {
            foreach ($order->extra_items as $product) {
                if ($product->returned_quantity > 0) {
                    $total += number_format((number_format(($product->price / (1 + $taxValue)), 9, '.', '') * $product->returned_quantity), 9, '.', '');
                }
            }
        }

        return $total;
    }
    private function calcOldFinalTotal($order, $taxValue)
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

        $orders = ReturnOrder::where('status', 'COMPLETED')
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

        foreach ($orders as $key => $order) {
            ProcessOrderToFatora::dispatch($order, $userId);  // Consider using a specific queue


        }

        return response()->json([
            'status' => 'queued',
            'message' => $orders->count() . ' orders have been queued for migration',
            'order_ids' => $orders->pluck('id')
        ]);
    }

}
