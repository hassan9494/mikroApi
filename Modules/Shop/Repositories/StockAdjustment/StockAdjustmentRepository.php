<?php

namespace Modules\Shop\Repositories\StockAdjustment;

use App\Repositories\Base\EloquentRepository;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class StockAdjustmentRepository extends EloquentRepository implements StockAdjustmentRepositoryInterface
{
    public function __construct(StockAdjustment $model)
    {
        parent::__construct($model);
    }

    public function createRequest($data)
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'pending';

        // Handle transfer adjustments
        if ($data['adjustment_type'] === 'transfer') {
            $data['adjustment_location'] = 'transfer';
        }

        return $this->model->create($data);
    }

    public function approveRequest($id, $approvedBy, $notes = null)
    {
        DB::beginTransaction();

        try {
            $adjustment = $this->findOrFail($id);

            // Check if already approved/rejected
            if ($adjustment->status !== 'pending') {
                throw new \Exception('Adjustment is already processed');
            }

            // Get product with lock to prevent race conditions
            $product = $adjustment->product()->lockForUpdate()->first();

            // Store historical stock values for all locations
            $stockBefore = $product->stock;
            $stockAvailableBefore = $product->stock_available ?? 0;
            $storeAvailableBefore = $product->store_available ?? 0;

            // Calculate new stock based on adjustment type
            if ($adjustment->adjustment_type === 'transfer') {
                // Handle transfer between locations
                $result = $this->handleTransferAdjustment($product, $adjustment);
                $stockAfter = $result['stock_after'];
                $stockAvailableAfter = $result['stock_available_after'];
                $storeAvailableAfter = $result['store_available_after'];
            } else {
                // Handle regular increase/decrease
                $result = $this->handleRegularAdjustment($product, $adjustment);
                $stockAfter = $result['stock_after'];
                $stockAvailableAfter = $result['stock_available_after'];
                $storeAvailableAfter = $result['store_available_after'];
            }

            // Update adjustment with historical data
            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'notes' => $notes,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'stock_after_calculated' => $stockAfter,
                'stock_available_before' => $stockAvailableBefore,
                'stock_available_after' => $stockAvailableAfter,
                'store_available_before' => $storeAvailableBefore,
                'store_available_after' => $storeAvailableAfter
            ]);

            // Update product
            $product->stock = $stockAfter;
            $product->stock_available = $stockAvailableAfter;
            $product->store_available = $storeAvailableAfter;
            $product->save();

            DB::commit();

            return $adjustment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleRegularAdjustment($product, $adjustment)
    {
        $stockBefore = $product->stock;
        $stockAvailableBefore = $product->stock_available ?? 0;
        $storeAvailableBefore = $product->store_available ?? 0;

        $adjustmentLocation = $adjustment->adjustment_location ?? 'total';
        $quantity = $adjustment->quantity;

        if ($adjustment->adjustment_type === 'increase') {
            if ($adjustmentLocation === 'total') {
                // Default: add to store_available when increasing total
                $stockAvailableAfter = $stockAvailableBefore;
                $storeAvailableAfter = $storeAvailableBefore + $quantity;
                $stockAfter = $stockBefore + $quantity;
            } elseif ($adjustmentLocation === 'stock_available') {
                $stockAvailableAfter = $stockAvailableBefore + $quantity;
                $storeAvailableAfter = $storeAvailableBefore;
                $stockAfter = $stockBefore + $quantity;
            } else { // store_available
                $stockAvailableAfter = $stockAvailableBefore;
                $storeAvailableAfter = $storeAvailableBefore + $quantity;
                $stockAfter = $stockBefore + $quantity;
            }
        } else { // decrease
            if ($adjustmentLocation === 'total') {
                // Default: take from store_available first, then stock_available
                if ($storeAvailableBefore >= $quantity) {
                    $stockAvailableAfter = $stockAvailableBefore;
                    $storeAvailableAfter = $storeAvailableBefore - $quantity;
                } else {
                    $remaining = $quantity - $storeAvailableBefore;
                    $stockAvailableAfter = max(0, $stockAvailableBefore - $remaining);
                    $storeAvailableAfter = 0;
                }
                $stockAfter = max(0, $stockBefore - $quantity);
            } elseif ($adjustmentLocation === 'stock_available') {
                $stockAvailableAfter = max(0, $stockAvailableBefore - $quantity);
                $storeAvailableAfter = $storeAvailableBefore;
                $stockAfter = max(0, $stockBefore - $quantity);
            } else { // store_available
                $stockAvailableAfter = $stockAvailableBefore;
                $storeAvailableAfter = max(0, $storeAvailableBefore - $quantity);
                $stockAfter = max(0, $stockBefore - $quantity);
            }
        }

        return [
            'stock_after' => $stockAfter,
            'stock_available_after' => $stockAvailableAfter,
            'store_available_after' => $storeAvailableAfter
        ];
    }

    private function handleTransferAdjustment($product, $adjustment)
    {
        $stockBefore = $product->stock;
        $stockAvailableBefore = $product->stock_available ?? 0;
        $storeAvailableBefore = $product->store_available ?? 0;
        $quantity = $adjustment->quantity;

        // Validate transfer
        if ($adjustment->transfer_from_location === 'stock_available') {
            if ($stockAvailableBefore < $quantity) {
                throw new \Exception('Not enough stock in Stock Available for transfer');
            }
            $stockAvailableAfter = $stockAvailableBefore - $quantity;
            $storeAvailableAfter = $storeAvailableBefore + $quantity;
        } else { // transfer_from_location === 'store_available'
            if ($storeAvailableBefore < $quantity) {
                throw new \Exception('Not enough stock in Store Available for transfer');
            }
            $stockAvailableAfter = $stockAvailableBefore + $quantity;
            $storeAvailableAfter = $storeAvailableBefore - $quantity;
        }

        // Total stock remains the same for transfers
        $stockAfter = $stockBefore;

        return [
            'stock_after' => $stockAfter,
            'stock_available_after' => $stockAvailableAfter,
            'store_available_after' => $storeAvailableAfter
        ];
    }

    public function rejectRequest($id, $rejectedBy, $reason = null)
    {
        DB::beginTransaction();

        try {
            $adjustment = $this->findOrFail($id);

            // Check if already approved/rejected
            if ($adjustment->status !== 'pending') {
                throw new \Exception('Adjustment is already processed');
            }

            // Get current product stock
            $product = $adjustment->product;
            $currentStock = $product ? $product->stock : 0;
            $currentStockAvailable = $product ? $product->stock_available : 0;
            $currentStoreAvailable = $product ? $product->store_available : 0;

            // Store historical stock values
            $adjustment->update([
                'status' => 'rejected',
                'approved_by' => $rejectedBy,
                'rejection_reason' => $reason,
                'approved_at' => now(),
                'stock_before' => $currentStock,
                'stock_after' => $currentStock,
                'stock_after_calculated' => $currentStock,
                'stock_available_before' => $currentStockAvailable,
                'stock_available_after' => $currentStockAvailable,
                'store_available_before' => $currentStoreAvailable,
                'store_available_after' => $currentStoreAvailable
            ]);

            DB::commit();

            return $adjustment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function applyStockAdjustment($adjustment)
    {
        if (is_numeric($adjustment)) {
            $adjustment = $this->findOrFail($adjustment);
        }

        if (!$adjustment instanceof StockAdjustment || $adjustment->status !== 'approved') {
            return false;
        }

        $product = $adjustment->product;

        if ($adjustment->adjustment_type === 'increase') {
            $product->stock  += $adjustment->quantity;
        } else {
            $product->stock  = max(0, $product->stock  - $adjustment->quantity);
        }

        return $product->save();
    }

    public function processRejection($adjustment, $rejectedBy, $reason = null)
    {
        // Get current product stock
        $product = $adjustment->product;
        $currentStock = $product ? $product->stock : 0;

        $adjustment->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy,
            'rejection_reason' => $reason,
            'approved_at' => now(),
            'stock_before' => $currentStock,
            'stock_after' => $currentStock,
            'stock_after_calculated' => $currentStock
        ]);

        return $adjustment;
    }

    public function processApproval($adjustment, $approvedBy, $notes = null)
    {
        // Get product with lock
        $product = $adjustment->product()->lockForUpdate()->first();

        // Store historical values
        $stockBefore = $product->stock;

        if ($adjustment->adjustment_type === 'increase') {
            $stockAfter = $stockBefore + $adjustment->quantity;
        } else {
            $stockAfter = max(0, $stockBefore - $adjustment->quantity);
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'notes' => $notes,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'stock_after_calculated' => $stockAfter
        ]);

        // Update product stock
        $product->stock = $stockAfter;
        $product->save();

        return $adjustment;
    }

    public function getPendingRequests($filters = [])
    {
        $query = $this->model->pending()->with(['product', 'user']);

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })->orWhereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
    }

    public function getAllRequests($filters = [])
    {
        $query = $this->model->with(['product', 'user', 'approver']);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })->orWhereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
    }

    public function getUserRequests($userId, $filters = [])
    {
        $query = $this->model->where('user_id', $userId)->with(['product', 'approver']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
    }

    public function getByProduct($productId, $filters = [])
    {
        $query = $this->model->where('product_id', $productId)->with(['user', 'approver']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['limit'] ?? 20);
    }

    public function getStatistics($userId = null, $startDate = null, $endDate = null)
    {
        $query = $this->model->query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [
            'total_requests' => $query->count(),
            'pending_requests' => $query->where('status', 'pending')->count(),
            'approved_requests' => $query->where('status', 'approved')->count(),
            'rejected_requests' => $query->where('status', 'rejected')->count(),
            'total_increase' => $query->where('status', 'approved')
                ->where('adjustment_type', 'increase')
                ->sum('quantity'),
            'total_decrease' => $query->where('status', 'approved')
                ->where('adjustment_type', 'decrease')
                ->sum('quantity')
        ];
    }

}
