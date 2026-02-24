<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Admin\Http\Resources\BulkOrderCompletionResource;
use Modules\Admin\Http\Resources\BulkOrderListResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Services\BulkOrderCompletionService;
use Modules\Shop\Entities\BulkOrderCompletion;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PaymentMethod;

class BulkOrderCompletionController extends Controller
{
    protected $bulkCompletionService;

    public function __construct(BulkOrderCompletionService $bulkCompletionService)
    {
        $this->bulkCompletionService = $bulkCompletionService;
    }

    /**
     * Get orders for bulk completion list
     */
    public function index(Request $request)
    {
        $request->validate([
            'shipping_provider_id' => 'nullable|exists:shipping_providers,id',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
        ]);

        $query = Order::with(['products', 'transactions', 'shippingProvider'])
            ->where('options->dept',false)
            ->whereIn('status', ['PENDING', 'PROCESSING'])->Where('shipping_provider_id','>',0);

        // Filter by shipping provider
        if ($request->has('shipping_provider_id') && (int)$request->shipping_provider_id > 0) {
            $query->where('shipping_provider_id', $request->shipping_provider_id);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
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

        // Pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $orders = $query->orderBy('id','desc')->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => BulkOrderListResource::collection($orders),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    public function datatable(): JsonResponse
    {
        $conditions = json_decode(request('conditions'), true) ?? [];
        $search = request('search');
        $order = json_decode(request('order'), true);
        $page = request('page', 0);
        $limit = request('limit', 10);

        //  $query = Order::query()->with('histories.user');
        $query = BulkOrderCompletion::query();

        // Apply search conditions
        if (!empty($search)) {
//            dd($search);
            $query->where(function ($q) use ($search) {
                $q->where('id', '=', $search);
            });
        }

//        // Handle different condition formats
//        if (!empty($conditions)) {
//
//            // If conditions is an associative array (object format)
//            if (isset($conditions['status']) || isset($conditions['options->taxed'])) {
//
//                foreach ($conditions as $key => $value) {
//                    if (str_contains($key, '->')) {
//                        $query->where("$key", $value);
//                    } else {
//                        $query->where($key, $value);
//                    }
//                }
//            } // If conditions is an array of condition objects
//            else if (is_array($conditions) && isset($conditions[0]['col'])) {
//
//                foreach ($conditions as $condition) {
//
//                    if (isset($condition['col']) && isset($condition['op']) && isset($condition['val'])) {
//                        $column = $condition['col'];
//                        $operator = $condition['op'];
//                        $value = $condition['val'];
//
//                        // Handle IN operator
//                        if ($operator === 'IN') {
//                            if (str_contains($column, '->')) {
//                                $query->whereIn("$column", $value);
//                            } else {
//                                $query->whereIn($column, $value);
//                            }
//                        } else {
//                            if (str_contains($column, '->')) {
//                                $query->where("$column", $operator, $value);
//                            } else {
//                                $query->where($column, $operator, $value);
//                            }
//                        }
//                    } elseif (isset($condition['col']) && isset($condition['val'])) {
//                        $column = $condition['col'];
//                        $value = $condition['val'];
//
//                        if (str_contains($column, '->')) {
//                            $query->where("$column", $value);
//                        } else {
//                            $query->where($column, $value);
//                        }
//                    }
//                }
//            } else {
//                foreach ($conditions as $key => $value) {
//                    if ($key == 'not_admin' && $value == 1) {
//                        $query->where(function ($q) {
//                            $q->where('status', '!=', 'completed')
//                                ->orWhere('options->taxed', true);
//                        });
//                    } else {
//                        $query->where($key, $value);
//                    }
//
//                }
//            }
//        }

        // Apply ordering
        if (!empty($order) && isset($order['column']) && isset($order['dir'])) {
            $query->orderBy($order['column'], $order['dir']);
        }

        // Get paginated results
        $total = $query->count();
        $items = $query->skip($page * $limit)->take($limit)->get();

        return response()->json([
            'data' => [
                'items' => BulkOrderCompletionResource::collection($items),
                'total' => $total
            ]
        ]);
    }

    public function show($id)
    {
        $bulkOrderCompletion = BulkOrderCompletion::with([
            'user',
            'paymentMethod',
            'bulkCompletionOrders',
            'orders.user',
            'orders.cashier',
            'orders.shippingProvider',
            'orders.products',
            'orders.transactions'
        ])->findOrFail($id);

        return new BulkOrderCompletionResource($bulkOrderCompletion);
    }

    /**
     * Get payment methods for bulk completion
     */
    public function paymentMethods()
    {
        $paymentMethods = PaymentMethod::all();
        return response()->json($paymentMethods);
    }

    /**
     * Preview bulk completion (calculate values)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);
        $validation = $this->bulkCompletionService->validateOrders($request->order_ids);

        if (!$validation['valid']) {
            return response()->json([
                'errors' => $validation['errors']
            ], 422);
        }

        $orders = $validation['orders'];
        $calculatedOrders = [];
        $totals = [
            'calculated_amount' => 0,
            'calculated_shipping' => 0,
            'calculated_commission' => 0,
        ];

        foreach ($orders as $order) {
            $details = $this->bulkCompletionService->calculateOrderDetails($order, $paymentMethod);

            $calculatedOrders[] = [
                'order' => $order,
                'calculated_amount' => $details['remaining_balance'],
                'calculated_shipping' => $details['calculated_shipping'],
                'calculated_commission' => $details['calculated_commission'],
                'max_amount' => $details['max_amount'],
                'order_total' => $details['order_total'],
                'existing_payments' => $details['existing_payments'],
                'shipping_free' => $details['shipping_free'],
            ];

            $totals['calculated_amount'] += $details['remaining_balance'];
            $totals['calculated_shipping'] += $details['calculated_shipping'];
            $totals['calculated_commission'] += $details['calculated_commission'];
        }

        return response()->json([
            'orders' => $calculatedOrders,
            'totals' => $totals,
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Process bulk completion
     */
    public function complete(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'delivery_number' => 'nullable|string',
            'delivery_date' => 'nullable|date',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'totals' => 'required|array',
            'totals.entered_amount' => 'required|numeric|min:0',
            'totals.entered_shipping' => 'required|numeric|min:0',
            'totals.entered_commission' => 'required|numeric',
            'orders_data' => 'required|array',
            'orders_data.*.entered_amount' => 'required|numeric',
            'orders_data.*.entered_shipping' => 'required|numeric|min:0',
            'orders_data.*.entered_commission' => 'required|numeric',
        ]);

        $user = Auth::user();

        try {
            $bulkCompletion = $this->bulkCompletionService->processBulkCompletion($request->all(), $user);

            return response()->json([
                'message' => 'Bulk order completion successful',
                'bulk_completion' => $bulkCompletion,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Bulk order completion failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
