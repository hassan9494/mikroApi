<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Models\StockCount;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Admin\Http\Resources\StockCountResource;
use Modules\Admin\Http\Resources\StockCountProductResource;
use Modules\Shop\Repositories\StockCount\StockCountRepositoryInterface;

class StockCountController extends Controller
{
    use ApiResponser;

    protected $repository;

    public function __construct()
    {
        $this->repository = app()->make(StockCountRepositoryInterface::class);
    }

    /**
     * Check if current user is an admin (super/admin/Stock Manager)
     */
    private function isAdmin(): bool
    {
        $user = auth()->user();
        return $user && $user->can('stock_count_approve');
    }

    /**
     * Ensure user can access a specific count (owner or admin)
     */
    private function authorizeAccess(StockCount $count): void
    {
        if (!$this->isAdmin() && $count->user_id !== auth()->id()) {
            abort(403, 'You can only access your own stock counts.');
        }
    }

    /**
     * List stock counts - admins see all, normal users see only their own
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = $request->only(['status', 'user_id', 'search', 'start_date', 'end_date']);
        $filters['limit'] = $request->get('limit', 20);

        if ($this->isAdmin()) {
            $counts = $this->repository->getAllCounts($filters);
        } else {
            $counts = $this->repository->getUserCounts($user->id, $filters);
        }

        return response()->json([
            'data' => [
                'items' => StockCountResource::collection($counts),
                'total' => $counts->total(),
            ]
        ]);
    }

    /**
     * Create a new stock count (draft)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|exists:products,id',
            'products.*.store_available_counted' => 'nullable|integer|min:0',
            'products.*.stock_available_counted' => 'nullable|integer|min:0',
            'products.*.notes' => 'nullable|string|max:500',
        ]);

        $count = $this->repository->createCount($data);

        return $this->success([
            'message' => 'Stock count created successfully',
            'data' => new StockCountResource($count),
        ], null, 201);
    }

    /**
     * View a single stock count
     */
    public function show($id): JsonResponse
    {
        $count = $this->repository->findOrFail($id, ['products.product', 'user', 'approver']);

        $this->authorizeAccess($count);

        return $this->success(new StockCountResource($count));
    }

    /**
     * Update a stock count (draft or pending)
     */
    public function update($id, Request $request): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        $this->authorizeAccess($count);

        if (!$count->is_editable) {
            return $this->error('This count cannot be edited.', 403);
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $count = $this->repository->updateCount($id, $data);

        return $this->success([
            'message' => 'Stock count updated successfully',
            'data' => new StockCountResource($count),
        ]);
    }

    /**
     * Add products to a stock count
     */
    public function addProducts($id, Request $request): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        $this->authorizeAccess($count);

        if (!$count->is_editable) {
            return $this->error('Cannot add products to this count.', 403);
        }

        $data = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.store_available_counted' => 'nullable|integer|min:0',
            'products.*.stock_available_counted' => 'nullable|integer|min:0',
            'products.*.notes' => 'nullable|string|max:500',
        ]);

        $addedProducts = $this->repository->addProducts($id, $data['products']);

        return $this->success([
            'message' => count($addedProducts) . ' product(s) added successfully',
            'data' => StockCountProductResource::collection($addedProducts),
        ]);
    }

    /**
     * Update a product's counted values
     */
    public function updateProduct($id, $productId, Request $request): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        $this->authorizeAccess($count);

        if (!$count->is_editable) {
            return $this->error('Cannot update products in this count.', 403);
        }

        $data = $request->validate([
            'store_available_counted' => 'nullable|integer|min:0',
            'stock_available_counted' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $countProduct = $this->repository->updateProduct($id, $productId, $data);

        return $this->success([
            'message' => 'Product count updated successfully',
            'data' => new StockCountProductResource($countProduct),
        ]);
    }

    /**
     * Remove a product from a stock count
     */
    public function removeProduct($id, $productId): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        $this->authorizeAccess($count);

        if (!$count->is_editable) {
            return $this->error('Cannot remove products from this count.', 403);
        }

        $this->repository->removeProduct($id, $productId);

        return $this->success([
            'message' => 'Product removed from count successfully',
        ]);
    }

    /**
     * Submit a stock count for approval (draft → pending)
     */
    public function submit($id): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        // Only the creator can submit their own count
        if ($count->user_id !== auth()->id()) {
            return $this->error('Only the creator can submit a stock count.', 403);
        }

        $count = $this->repository->submitCount($id);

        return $this->success([
            'message' => 'Stock count submitted for approval',
            'data' => new StockCountResource($count),
        ]);
    }

    /**
     * Get current user's stock counts
     */
    public function myCounts(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = $request->only(['status', 'search', 'start_date', 'end_date']);
        $filters['limit'] = $request->get('limit', 20);

        $counts = $this->repository->getUserCounts($user->id, $filters);

        return response()->json([
            'data' => [
                'items' => StockCountResource::collection($counts),
                'total' => $counts->total(),
            ]
        ]);
    }

    /**
     * Approve a stock count (overwrites product stock)
     */
    public function approve($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_count_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $count = $this->repository->approveCount($id, auth()->id(), $data['notes'] ?? null);

        return $this->success([
            'message' => 'Stock count approved and stock quantities updated',
            'data' => new StockCountResource($count),
        ]);
    }

    /**
     * Reject a stock count
     */
    public function reject($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_count_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $count = $this->repository->rejectCount($id, auth()->id(), $data['reason'] ?? null);

        return $this->success([
            'message' => 'Stock count rejected',
            'data' => new StockCountResource($count),
        ]);
    }

    /**
     * Change status of a stock count (approved/rejected → pending)
     */
    public function changeStatus($id, Request $request): JsonResponse
    {
        if (!Gate::allows('stock_count_approve')) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:pending',
            'reason' => 'nullable|string|max:500',
        ]);

        $count = $this->repository->changeStatus($id, $data['status'], auth()->id(), $data['reason'] ?? null);

        return $this->success([
            'message' => 'Stock count status changed to pending',
            'data' => new StockCountResource($count),
        ]);
    }

    /**
     * Datatable view for stock counts
     */
    public function datatable(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = StockCount::query()->with(['products.product', 'user', 'approver']);

        // Normal users can only see their own counts
        if (!$this->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('products.product', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id') && $this->isAdmin()) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => [
                'items' => StockCountResource::collection($items),
                'total' => $total,
            ]
        ]);
    }

    /**
     * View audit history for a stock count
     */
    public function history($id): JsonResponse
    {
        $count = $this->repository->findOrFail($id);

        $this->authorizeAccess($count);

        $history = $this->repository->getCountHistory($id);

        return $this->success([
            'data' => $history,
        ]);
    }
}
