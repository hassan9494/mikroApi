<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Admin\Http\Resources\StockAdjustmentResource;
use Modules\Shop\Repositories\StockAdjustment\StockAdjustmentRepositoryInterface;
use App\Models\StockAdjustment;

class StockAdjustmentController extends Controller
{
    use ApiResponser;

    protected $repository;

    public function __construct()
    {
        $this->repository = app()->make(\Modules\Shop\Repositories\StockAdjustment\StockAdjustmentRepositoryInterface::class);
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user can view all requests
        if ($user->can('stock_adjustment_list_view')) {
            // Admin/Super/Stock Manager: show all pending requests for approval page
            $filters = $request->only(['status', 'product_id', 'user_id', 'search', 'start_date', 'end_date']);
            $filters['limit'] = $request->get('limit', 20);

            if (!isset($filters['status']) || $filters['status'] === 'pending') {
                $adjustments = $this->repository->getPendingRequests($filters);
            } else {
                $adjustments = $this->repository->getAllRequests($filters);
            }
        } else {
            // Regular users: only show their own requests
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($adjustments),
                'total' => $adjustments->total()
            ]
        ]);
    }

    public function getAllRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = $request->only(['status', 'product_id', 'search', 'start_date', 'end_date', 'user_id']);
        $filters['limit'] = $request->get('limit', 20);

        // Check if user can view all requests
        if ($user->can('stock_adjustment_list_view')) {
            // Admin/Super/Stock Manager: show all requests with optional filters
            $query = StockAdjustment::query()->with(['product', 'user', 'approver']);

            // DON'T apply any status filter unless it's a specific status (not 'all')
            if (isset($filters['status']) && $filters['status'] && $filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            }
            // If status is 'all' or not provided, show ALL statuses

            if (isset($filters['search']) && $filters['search']) {
                $search = $filters['search'];
                $query->where(function($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('product', function($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by user_id if provided
            if (isset($filters['user_id']) && $filters['user_id']) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['start_date'])) {
                $query->whereDate('created_at', '>=', $filters['start_date']);
            }

            if (isset($filters['end_date'])) {
                $query->whereDate('created_at', '<=', $filters['end_date']);
            }

            $adjustments = $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
        } else {
            // Regular users: show only their own requests
            $query = StockAdjustment::query()->where('user_id', $user->id)->with(['product', 'approver']);

            // DON'T apply any status filter unless it's a specific status (not 'all')
            if (isset($filters['status']) && $filters['status'] && $filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['search']) && $filters['search']) {
                $search = $filters['search'];
                $query->whereHas('product', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if (isset($filters['start_date'])) {
                $query->whereDate('created_at', '>=', $filters['start_date']);
            }

            if (isset($filters['end_date'])) {
                $query->whereDate('created_at', '<=', $filters['end_date']);
            }

            $adjustments = $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
        }

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($adjustments),
                'total' => $adjustments->total()
            ]
        ]);

    }

    public function store(Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_request')) {
            abort(403);
        }
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_type' => 'required|in:increase,decrease,transfer',
            'adjustment_location' => 'nullable|in:total,stock_available,store_available',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
            'transfer_from_location' => 'required_if:adjustment_type,transfer|in:1,2', // 1 for stock_available, 2 for store_available
            'transfer_to_location' => 'required_if:adjustment_type,transfer|in:1,2|different:transfer_from_location'
        ]);

// Map integer values to string representations
        $locationMap = [
            1 => 'stock_available',
            2 => 'store_available'
        ];

        if ($data['adjustment_type'] === 'transfer') {
            $data['adjustment_location'] = 'transfer';
            $data['transfer_from_location'] = $locationMap[$data['transfer_from_location']] ?? null;
            $data['transfer_to_location'] = $locationMap[$data['transfer_to_location']] ?? null;
        } else {
            $data['adjustment_location'] = $data['adjustment_location'] ?? 'total';
        }


        $adjustment = $this->repository->createRequest($data);

        return $this->success([
            'message' => 'Stock adjustment request submitted successfully',
            'data' => new StockAdjustmentResource($adjustment)
        ], 201);
    }

    public function approve($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }



        $adjustment = $this->repository->approveRequest($id, auth()->id());

        return $this->success([
            'message' => 'Stock adjustment approved successfully',
            'data' => new StockAdjustmentResource($adjustment)
        ]);
    }

    public function reject($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }



        $adjustment = $this->repository->rejectRequest($id, auth()->id());

        return $this->success([
            'message' => 'Stock adjustment rejected',
            'data' => new StockAdjustmentResource($adjustment)
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = $request->only(['status', 'start_date', 'end_date']);
        $filters['limit'] = $request->get('limit', 20);

        $adjustments = $this->repository->getUserRequests($user->id, $filters);

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($adjustments),
                'total' => $adjustments->total()
            ]
        ]);
    }

    public function show($id): JsonResponse
    {
        $adjustment = $this->repository->findOrFail($id, ['product', 'user', 'approver']);

        // Check if user can view this adjustment
        $user = auth()->user();
        if (!$user->can('stock_adjustment_approve') && $adjustment->user_id !== $user->id) {
            abort(403);
        }

        return $this->success(new StockAdjustmentResource($adjustment));
    }

    public function statistics(Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $statistics = $this->repository->getStatistics(
            $data['user_id'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null
        );

        return $this->success($statistics);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:stock_adjustments,id',
            'notes' => 'nullable|string|max:500'
        ]);

        $approved = $this->repository->bulkApprove($data['ids'], auth()->id());

        return $this->success([
            'message' => count($approved) . ' adjustments approved successfully',
            'data' => StockAdjustmentResource::collection($approved)
        ]);
    }

    public function bulkReject(Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:stock_adjustments,id',
            'reason' => 'required|string|max:500'
        ]);

        $rejected = $this->repository->bulkReject($data['ids'], auth()->id(), $data['reason']);

        return $this->success([
            'message' => count($rejected) . ' adjustments rejected',
            'data' => $rejected
        ]);
    }

    public function productHistory($productId, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }

        $filters = $request->only(['status']);
        $filters['limit'] = $request->get('limit', 20);

        $history = $this->repository->getByProduct($productId, $filters);

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($history),
                'total' => $history->total()
            ]
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_list_view')) {
            abort(403);
        }

        $query = StockAdjustment::query()->with(['product', 'user', 'approver']);

        // Add search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('product', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Add filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date filters
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($items),
                'total' => $total
            ]
        ]);
    }

    public function myRequestsDatatable(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user can view all or only their own
        if ($user->can('stock_adjustment_list_view')) {
            $query = StockAdjustment::query()->with(['product', 'user', 'approver']);
        } else {
            $query = StockAdjustment::query()
                ->where('user_id', $user->id)
                ->with(['product', 'approver']);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => [
                'items' => StockAdjustmentResource::collection($items),
                'total' => $total
            ]
        ]);
    }
    public function update($id, Request $request): JsonResponse
    {
        $adjustment = $this->repository->findOrFail($id);

        // Check if user can edit this adjustment
        $user = auth()->user();
        if (!$adjustment->is_editable && $adjustment->user_id !== $user->id) {
            abort(403, 'You are not authorized to edit this adjustment.');
        }


        $data = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'adjustment_type' => 'sometimes|in:increase,decrease,transfer',
            'adjustment_location' => 'nullable|in:total,stock_available,store_available',
            'quantity' => 'sometimes|integer|min:1',
            'reason' => 'nullable|string|max:500',
            'transfer_from_location' => 'required_if:adjustment_type,transfer|in:1,2',
            'transfer_to_location' => 'required_if:adjustment_type,transfer|in:1,2|different:transfer_from_location'
        ]);

        // Map integer values to string representations for transfer
        $locationMap = [
            1 => 'stock_available',
            2 => 'store_available'
        ];

        if (isset($data['adjustment_type']) && $data['adjustment_type'] === 'transfer') {
            $data['adjustment_location'] = 'transfer';
            if (isset($data['transfer_from_location'])) {
                $data['transfer_from_location'] = $locationMap[$data['transfer_from_location']] ?? null;
            }
            if (isset($data['transfer_to_location'])) {
                $data['transfer_to_location'] = $locationMap[$data['transfer_to_location']] ?? null;
            }
        } else {
            $data['adjustment_location'] = $data['adjustment_location'] ?? 'total';
        }

        $adjustment = $this->repository->updateRequest($id, $data);

        return $this->success([
            'message' => 'Stock adjustment updated successfully',
            'data' => new StockAdjustmentResource($adjustment)
        ]);
    }
    /**
     * Change status of a stock adjustment
     */
    public function changeStatus($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_adjustment_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:500'
        ]);

        $adjustment = $this->repository->changeStatus(
            $id,
            $data['status'],
            auth()->id(),
            $data['reason'] ?? null
        );

        return $this->success([
            'message' => 'Stock adjustment status changed successfully',
            'data' => new StockAdjustmentResource($adjustment)
        ]);
    }
    /**
     * Get adjustment for editing
     */
    public function edit($id): JsonResponse
    {
        $adjustment = $this->repository->getForEditing($id);

        return $this->success(new StockAdjustmentResource($adjustment));
    }
}
