<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Admin\Http\Resources\AccountStatementResource;
use Modules\Admin\Http\Resources\OrderForStatementResource;
use Modules\Admin\Http\Services\AccountStatementService;
use Modules\Shop\Entities\AccountStatement;
use Modules\Shop\Entities\Order;

class AccountStatementController extends Controller
{
    protected $service;

    public function __construct(AccountStatementService $service)
    {
        $this->service = $service;
    }

    /**
     * List orders eligible for statement (filter by status).
     */
    public function orders(Request $request)
    {
        $request->validate([
            'status' => 'nullable|string|in:PENDING,PROCESSING,COMPLETED,CANCELED',
            'page'   => 'nullable|integer|min:1',
            'limit'  => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
        ]);

        $query = Order::with(['transactions']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
        $page  = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $orders = $query->orderBy('id', 'desc')->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => OrderForStatementResource::collection($orders),
            'meta' => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ]
        ]);
    }

    /**
     * Preview statement calculations before saving.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
        ]);

        $validation = $this->service->validateOrders($request->order_ids);
        if (!$validation['valid']) {
            return response()->json(['errors' => $validation['errors']], 422);
        }

        $calculated = $this->service->calculateDetails(
            $validation['orders'],
            $request->from_date,
            $request->to_date
        );

        return response()->json([
            'orders'   => OrderForStatementResource::collection($validation['orders']),
            'details'  => $calculated['details'],
            'totals'   => [
                'total_debtor'  => $calculated['total_debtor'],
                'total_creditor' => $calculated['total_creditor'],
                'balance'       => $calculated['balance'],
            ],
        ]);
    }

    /**
     * Store a new account statement.
     */
    public function store(Request $request)
    {
        $request->validate([
            'order_ids'       => 'required|array',
            'order_ids.*'     => 'exists:orders,id',
            'account_number'  => 'nullable|string|max:255',
            'account_name'    => 'nullable|string|max:255',
            'address'         => 'nullable|string',
            'phone'           => 'nullable|string|max:50',
            'from_date'       => 'nullable|date',
            'to_date'         => 'nullable|date',
            'notes'           => 'nullable|string',
        ]);

        $user = Auth::user();

        // Validate orders again
        $validation = $this->service->validateOrders($request->order_ids);
        if (!$validation['valid']) {
            return response()->json(['errors' => $validation['errors']], 422);
        }

        // Calculate details
        $calculated = $this->service->calculateDetails(
            $validation['orders'],
            $request->from_date,
            $request->to_date
        );

        // Prepare data for creation
        $data = [
            'account_number'  => $request->account_number,
            'account_name'    => $request->account_name,
            'address'         => $request->address,
            'phone'           => $request->phone,
            'from_date'       => $request->from_date,
            'to_date'         => $request->to_date,
            'notes'           => $request->notes,
            'total_debtor'    => $calculated['total_debtor'],
            'total_creditor'  => $calculated['total_creditor'],
            'details'         => $calculated['details'],
        ];

        $statement = $this->service->createStatement($data, $user);

        return new AccountStatementResource($statement);
    }

    /**
     * Show a specific account statement.
     */
    public function show($id)
    {
        $statement = AccountStatement::with(['user', 'details.order'])->findOrFail($id);
        return new AccountStatementResource($statement);
    }

    /**
     * Datatable for listing statements.
     */
    public function datatable(Request $request)
    {
        // Similar to bulk order completion datatable
        $search = $request->get('search', '');
        $order = json_decode($request->get('order', '{}'), true);
        $page = $request->get('page', 0);
        $limit = $request->get('limit', 10);

        $query = AccountStatement::with('user');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('account_name', 'LIKE', "%{$search}%")
                    ->orWhere('account_number', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($order) && isset($order['column'], $order['dir'])) {
            $query->orderBy($order['column'], $order['dir']);
        }

        $total = $query->count();
        $items = $query->skip($page * $limit)->take($limit)->get();

        return response()->json([
            'data' => [
                'items' => AccountStatementResource::collection($items),
                'total' => $total,
            ]
        ]);
    }
}
