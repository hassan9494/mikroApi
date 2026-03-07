<?php

namespace Modules\Shop\Repositories\StockCount;

use App\Models\StockCount;
use App\Models\StockCountHistory;
use App\Models\StockCountProduct;
use App\Repositories\Base\EloquentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Shop\Entities\Product;

class StockCountRepository extends EloquentRepository implements StockCountRepositoryInterface
{
    public function __construct(StockCount $model)
    {
        parent::__construct($model);
    }

    public function createCount($data)
    {
        DB::beginTransaction();

        try {
            $count = $this->model->create([
                'user_id' => auth()->id(),
                'reference_number' => $this->generateReferenceNumber(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => auth()->id(),
                'action' => 'created',
            ]);

            // If products are provided in the creation request
            if (!empty($data['products'])) {
                $this->addProducts($count->id, $data['products']);
                $count->refresh();
                $this->autoPromoteIfComplete($count);
            }

            DB::commit();

            return $count->load('products.product', 'user');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateCount($id, $data)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($id);

            if (in_array($count->status, ['approved', 'rejected'])) {
                throw new \Exception('Cannot edit an approved or rejected count.');
            }

            $user = auth()->user();

            // Check permissions: creator can edit their own, admins can edit any pending
            if ($count->user_id !== $user->id && !$user->can('stock_count_approve')) {
                throw new \Exception('You are not authorized to edit this count.');
            }

            $updateData = [];
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            if (!empty($updateData)) {
                $count->update($updateData);

                StockCountHistory::create([
                    'stock_count_id' => $count->id,
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'field' => 'notes',
                    'new_value' => $data['notes'] ?? null,
                ]);
            }

            DB::commit();

            return $count->load('products.product', 'user');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function addProducts($countId, $products)
    {
        $count = $this->findOrFail($countId);

        if (in_array($count->status, ['approved', 'rejected'])) {
            throw new \Exception('Cannot add products to an approved or rejected count.');
        }

        $addedProducts = [];

        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            $product = Product::findOrFail($productId);

            // Check if product already exists in this count
            $existing = StockCountProduct::where('stock_count_id', $countId)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                continue; // Skip duplicates
            }

            $countProduct = StockCountProduct::create([
                'stock_count_id' => $countId,
                'product_id' => $productId,
                'store_available_expected' => $product->store_available ?? 0,
                'store_available_counted' => $productData['store_available_counted'] ?? null,
                'stock_available_expected' => $product->stock_available ?? 0,
                'stock_available_counted' => $productData['stock_available_counted'] ?? null,
                'total_expected' => $product->stock ?? 0,
                'notes' => $productData['notes'] ?? null,
            ]);

            // Calculate differences
            $countProduct->calculateDifferences();
            $countProduct->save();

            StockCountHistory::create([
                'stock_count_id' => $countId,
                'stock_count_product_id' => $countProduct->id,
                'user_id' => auth()->id(),
                'action' => 'product_added',
                'notes' => "Added product: {$product->name}",
            ]);

            $addedProducts[] = $countProduct;
        }

        // Auto-promote to pending if all products are fully counted
        $count->refresh();
        $this->autoPromoteIfComplete($count);

        return $addedProducts;
    }

    public function updateProduct($countId, $productId, $data)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($countId);

            if (in_array($count->status, ['approved', 'rejected'])) {
                throw new \Exception('Cannot update products in an approved or rejected count.');
            }

            $countProduct = StockCountProduct::where('stock_count_id', $countId)
                ->where('product_id', $productId)
                ->firstOrFail();

            $oldValues = [
                'store_available_counted' => $countProduct->store_available_counted,
                'stock_available_counted' => $countProduct->stock_available_counted,
            ];

            if (array_key_exists('store_available_counted', $data)) {
                $countProduct->store_available_counted = $data['store_available_counted'];
            }

            if (array_key_exists('stock_available_counted', $data)) {
                $countProduct->stock_available_counted = $data['stock_available_counted'];
            }

            if (isset($data['notes'])) {
                $countProduct->notes = $data['notes'];
            }

            // Recalculate differences
            $countProduct->calculateDifferences();
            $countProduct->save();

            // Map counted fields to their expected counterparts
            $expectedFieldMap = [
                'store_available_counted' => 'store_available_expected',
                'stock_available_counted' => 'stock_available_expected',
            ];

            // Log changes
            foreach (['store_available_counted', 'stock_available_counted'] as $field) {
                if (array_key_exists($field, $data) && $oldValues[$field] !== $countProduct->$field) {
                    // If old counted value was null, use the expected system value instead
                    $oldValue = $oldValues[$field] ?? $countProduct->{$expectedFieldMap[$field]};

                    StockCountHistory::create([
                        'stock_count_id' => $countId,
                        'stock_count_product_id' => $countProduct->id,
                        'user_id' => auth()->id(),
                        'action' => 'product_updated',
                        'field' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $countProduct->$field,
                    ]);
                }
            }

            // Auto-promote to pending if all products are fully counted
            $count->refresh();
            $this->autoPromoteIfComplete($count);

            DB::commit();

            return $countProduct->load('product');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeProduct($countId, $productId)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($countId);

            if (in_array($count->status, ['approved', 'rejected'])) {
                throw new \Exception('Cannot remove products from an approved or rejected count.');
            }

            $countProduct = StockCountProduct::where('stock_count_id', $countId)
                ->where('product_id', $productId)
                ->firstOrFail();

            $productName = $countProduct->product->name ?? 'Unknown';

            StockCountHistory::create([
                'stock_count_id' => $countId,
                'user_id' => auth()->id(),
                'action' => 'product_removed',
                'notes' => "Removed product: {$productName}",
            ]);

            $countProduct->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function submitCount($id)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($id);

            if ($count->status !== 'draft') {
                throw new \Exception('Only draft counts can be submitted.');
            }

            // Verify at least one product has store_available_counted filled
            $hasCountedProducts = $count->products()
                ->whereNotNull('store_available_counted')
                ->exists();

            if (!$hasCountedProducts) {
                throw new \Exception('At least one product must have a store count filled before submitting.');
            }

            $count->update([
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => auth()->id(),
                'action' => 'submitted',
            ]);

            DB::commit();

            return $count->load('products.product', 'user');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function approveCount($id, $approvedBy, $notes = null)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($id, ['products']);

            if ($count->status !== 'pending') {
                throw new \Exception('Only pending counts can be approved.');
            }

            foreach ($count->products as $countProduct) {
                // Skip products with no differences to apply
                if ($countProduct->store_available_difference === null && $countProduct->stock_available_difference === null) {
                    continue;
                }

                // Lock the product for update to prevent race conditions
                $product = Product::where('id', $countProduct->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    continue;
                }

                // Record old values in history
                $oldStoreAvailable = $product->store_available ?? 0;
                $oldStockAvailable = $product->stock_available ?? 0;
                $oldStock = $product->stock ?? 0;

                // Apply differences instead of overwriting
                // This way if qty changed during counting period (e.g. sales), it won't be lost
                if ($countProduct->store_available_difference !== null) {
                    $product->store_available = max(0, $oldStoreAvailable + $countProduct->store_available_difference);
                }

                if ($countProduct->stock_available_difference !== null) {
                    $product->stock_available = max(0, $oldStockAvailable + $countProduct->stock_available_difference);
                }

                // Recalculate total stock
                $product->stock = $product->store_available + $product->stock_available;
                $product->save();

                // Log stock changes per product
                StockCountHistory::create([
                    'stock_count_id' => $count->id,
                    'stock_count_product_id' => $countProduct->id,
                    'user_id' => $approvedBy,
                    'action' => 'stock_adjusted',
                    'field' => 'store_available',
                    'old_value' => $oldStoreAvailable,
                    'new_value' => $product->store_available,
                    'notes' => "Difference applied: {$countProduct->store_available_difference}",
                ]);

                StockCountHistory::create([
                    'stock_count_id' => $count->id,
                    'stock_count_product_id' => $countProduct->id,
                    'user_id' => $approvedBy,
                    'action' => 'stock_adjusted',
                    'field' => 'stock_available',
                    'old_value' => $oldStockAvailable,
                    'new_value' => $product->stock_available,
                    'notes' => "Difference applied: {$countProduct->stock_available_difference}",
                ]);

                StockCountHistory::create([
                    'stock_count_id' => $count->id,
                    'stock_count_product_id' => $countProduct->id,
                    'user_id' => $approvedBy,
                    'action' => 'stock_adjusted',
                    'field' => 'stock',
                    'old_value' => $oldStock,
                    'new_value' => $product->stock,
                ]);
            }

            $count->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => $approvedBy,
                'action' => 'approved',
                'notes' => $notes,
            ]);

            DB::commit();

            return $count->load('products.product', 'user', 'approver');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve stock count: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rejectCount($id, $rejectedBy, $reason = null)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($id);

            if ($count->status !== 'pending') {
                throw new \Exception('Only pending counts can be rejected.');
            }

            $count->update([
                'status' => 'rejected',
                'approved_by' => $rejectedBy,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => $rejectedBy,
                'action' => 'rejected',
                'notes' => $reason,
            ]);

            DB::commit();

            return $count->load('products.product', 'user');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function changeStatus($id, $status, $changedBy, $reason = null)
    {
        DB::beginTransaction();

        try {
            $count = $this->findOrFail($id, ['products']);

            if (!in_array($count->status, ['approved', 'rejected'])) {
                throw new \Exception('Only approved or rejected counts can have their status changed.');
            }

            if ($status !== 'pending') {
                throw new \Exception('Status can only be changed to pending.');
            }

            $oldStatus = $count->status;

            // If changing from approved → pending, reverse the stock differences
            if ($oldStatus === 'approved') {
                foreach ($count->products as $countProduct) {
                    if ($countProduct->store_available_difference === null && $countProduct->stock_available_difference === null) {
                        continue;
                    }

                    $product = Product::where('id', $countProduct->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        continue;
                    }

                    $oldStoreAvailable = $product->store_available ?? 0;
                    $oldStockAvailable = $product->stock_available ?? 0;
                    $oldStock = $product->stock ?? 0;

                    // Reverse the differences (allow negative)
                    if ($countProduct->store_available_difference !== null) {
                        $product->store_available = $oldStoreAvailable - $countProduct->store_available_difference;
                    }

                    if ($countProduct->stock_available_difference !== null) {
                        $product->stock_available = $oldStockAvailable - $countProduct->stock_available_difference;
                    }

                    $product->stock = $product->store_available + $product->stock_available;
                    $product->save();

                    StockCountHistory::create([
                        'stock_count_id' => $count->id,
                        'stock_count_product_id' => $countProduct->id,
                        'user_id' => $changedBy,
                        'action' => 'stock_reversed',
                        'field' => 'store_available',
                        'old_value' => $oldStoreAvailable,
                        'new_value' => $product->store_available,
                        'notes' => "Difference reversed: {$countProduct->store_available_difference}",
                    ]);

                    StockCountHistory::create([
                        'stock_count_id' => $count->id,
                        'stock_count_product_id' => $countProduct->id,
                        'user_id' => $changedBy,
                        'action' => 'stock_reversed',
                        'field' => 'stock_available',
                        'old_value' => $oldStockAvailable,
                        'new_value' => $product->stock_available,
                        'notes' => "Difference reversed: {$countProduct->stock_available_difference}",
                    ]);

                    StockCountHistory::create([
                        'stock_count_id' => $count->id,
                        'stock_count_product_id' => $countProduct->id,
                        'user_id' => $changedBy,
                        'action' => 'stock_reversed',
                        'field' => 'stock',
                        'old_value' => $oldStock,
                        'new_value' => $product->stock,
                    ]);
                }
            }

            $count->update([
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => $changedBy,
                'action' => 'status_changed',
                'field' => 'status',
                'old_value' => $oldStatus,
                'new_value' => 'pending',
                'notes' => $reason,
            ]);

            DB::commit();

            return $count->load('products.product', 'user', 'approver');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change stock count status: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getUserCounts($userId, $filters = [])
    {
        $query = $this->model->where('user_id', $userId)
            ->with(['products.product', 'approver']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['limit'] ?? 20);
    }

    public function getAllCounts($filters = [])
    {
        $query = $this->model->with(['products.product', 'user', 'approver']);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['limit'] ?? 20);
    }

    public function getCountHistory($countId)
    {
        return StockCountHistory::where('stock_count_id', $countId)
            ->with(['user', 'stockCountProduct.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function applyFilters($query, $filters)
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('products.product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
    }

    /**
     * Auto-promote draft to pending if all products have both store and stock counts filled
     */
    private function autoPromoteIfComplete(StockCount $count): void
    {
        if ($count->status !== 'draft') {
            return;
        }

        $totalProducts = $count->products()->count();

        if ($totalProducts === 0) {
            return;
        }

        // Check if ALL products have both store and stock counted values filled
        $fullyCountedProducts = $count->products()
            ->whereNotNull('store_available_counted')
            ->whereNotNull('stock_available_counted')
            ->count();

        if ($fullyCountedProducts === $totalProducts) {
            $count->update([
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            StockCountHistory::create([
                'stock_count_id' => $count->id,
                'user_id' => auth()->id(),
                'action' => 'submitted',
                'notes' => 'Auto-submitted: all products fully counted',
            ]);
        }
    }

    private function generateReferenceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "SC-{$date}-";

        $lastCount = $this->model
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderBy('reference_number', 'desc')
            ->first();

        if ($lastCount) {
            $lastNumber = (int) str_replace($prefix, '', $lastCount->reference_number);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
