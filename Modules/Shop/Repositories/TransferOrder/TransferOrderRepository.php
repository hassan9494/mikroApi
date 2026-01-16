<?php

namespace Modules\Shop\Repositories\TransferOrder;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\TransferOrder;
use Modules\Shop\Entities\TransferOrderProduct;
use Modules\Shop\Entities\TransferOrderHistory;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class TransferOrderRepository extends EloquentRepository
{
    public function __construct(TransferOrder $model)
    {
        parent::__construct($model);
    }

    public function createOrder($data)
    {
        DB::beginTransaction();

        try {
            // Create transfer order
            $transferOrder = TransferOrder::create([
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'PENDING'
            ]);

            // Add products
            foreach ($data['products'] as $productData) {
                TransferOrderProduct::create([
                    'transfer_order_id' => $transferOrder->id,
                    'product_id' => $productData['product_id'],
                    'from_location' => $productData['from_location'],
                    'to_location' => $productData['to_location'],
                    'quantity' => $productData['quantity']
                ]);
            }

            // Record history
            TransferOrderHistory::create([
                'transfer_order_id' => $transferOrder->id,
                'user_id' => auth()->id(),
                'action' => 'created',
                'notes' => 'Transfer order created'
            ]);

            DB::commit();

            return $transferOrder->load(['products.product', 'createdBy']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateOrder($id, $data)
    {
        $transferOrder = $this->findOrFail($id);

        if (!$transferOrder->can_edit) {
            throw new \Exception('Cannot edit completed or canceled transfer order');
        }

        DB::beginTransaction();

        try {
            // Store OLD products data BEFORE any changes
            $oldProducts = $transferOrder->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product->name ?? 'Unknown',
                    'from_location' => $product->from_location,
                    'to_location' => $product->to_location,
                    'quantity' => $product->quantity,
                    'stock_before' => $product->stock_before,
                    'stock_after' => $product->stock_after,
                    'stock_available_before' => $product->stock_available_before,
                    'stock_available_after' => $product->stock_available_after,
                    'store_available_before' => $product->store_available_before,
                    'store_available_after' => $product->store_available_after,
                ];
            })->toArray();

            // Update transfer order notes
            $oldNotes = $transferOrder->notes;
            $transferOrder->update([
                'notes' => $data['notes'] ?? null
            ]);

            // Record history if notes changed
            if ($oldNotes != $transferOrder->notes) {
                TransferOrderHistory::create([
                    'transfer_order_id' => $transferOrder->id,
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'field' => 'notes',
                    'change_type' => 'notes',
                    'old_value' => $oldNotes,
                    'new_value' => $transferOrder->notes,
                    'notes' => 'Notes updated from "' . ($oldNotes ?? 'empty') . '" to "' . ($transferOrder->notes ?? 'empty') . '"'
                ]);
            }

            // Delete existing products (but first capture their data for comparison)
            $oldProductIds = $transferOrder->products->pluck('id')->toArray();
            $transferOrder->products()->delete();

            // Add new products WITH STOCK HISTORY
            $newProductIds = [];
            foreach ($data['products'] as $productData) {
                $product = \Modules\Shop\Entities\Product::find($productData['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found: {$productData['product_id']}");
                }

                // Capture current stock values (BEFORE)
                $stockBefore = $product->stock_available + $product->store_available;
                $stockAvailableBefore = $product->stock_available;
                $storeAvailableBefore = $product->store_available;

                // Calculate expected AFTER values based on the transfer
                $stockAvailableAfter = $stockAvailableBefore;
                $storeAvailableAfter = $storeAvailableBefore;

                // Apply the transfer effect
                if ($productData['from_location'] === 'stock_available') {
                    $stockAvailableAfter -= $productData['quantity'];
                } else {
                    $storeAvailableAfter -= $productData['quantity'];
                }

                if ($productData['to_location'] === 'stock_available') {
                    $stockAvailableAfter += $productData['quantity'];
                } else {
                    $storeAvailableAfter += $productData['quantity'];
                }

                // Calculate total stock after
                $stockAfter = $stockAvailableAfter + $storeAvailableAfter;

                // Find if this product existed before
                $oldProduct = collect($oldProducts)->firstWhere('product_id', $productData['product_id']);

                if ($oldProduct) {
                    // Product existed before - check for changes

                    // Check quantity change
                    if ($oldProduct['quantity'] != $productData['quantity']) {
                        TransferOrderHistory::create([
                            'transfer_order_id' => $transferOrder->id,
                            'user_id' => auth()->id(),
                            'action' => 'quantity_changed',
                            'field' => 'quantity',
                            'change_type' => 'quantity',
                            'product_id' => $productData['product_id'],
                            'transfer_order_product_id' => $oldProduct['id'],
                            'old_value' => $oldProduct['quantity'],
                            'new_value' => $productData['quantity'],
                            'notes' => "Quantity for product '{$product->name}' changed from {$oldProduct['quantity']} to {$productData['quantity']}"
                        ]);
                    }

                    // Check from_location change
                    if ($oldProduct['from_location'] != $productData['from_location']) {
                        TransferOrderHistory::create([
                            'transfer_order_id' => $transferOrder->id,
                            'user_id' => auth()->id(),
                            'action' => 'from_location_changed',
                            'field' => 'from_location',
                            'change_type' => 'from_location',
                            'product_id' => $productData['product_id'],
                            'transfer_order_product_id' => $oldProduct['id'],
                            'old_value' => $oldProduct['from_location'],
                            'new_value' => $productData['from_location'],
                            'notes' => "From location for product '{$product->name}' changed from {$oldProduct['from_location']} to {$productData['from_location']}"
                        ]);
                    }

                    // Check to_location change
                    if ($oldProduct['to_location'] != $productData['to_location']) {
                        TransferOrderHistory::create([
                            'transfer_order_id' => $transferOrder->id,
                            'user_id' => auth()->id(),
                            'action' => 'to_location_changed',
                            'field' => 'to_location',
                            'change_type' => 'to_location',
                            'product_id' => $productData['product_id'],
                            'transfer_order_product_id' => $oldProduct['id'],
                            'old_value' => $oldProduct['to_location'],
                            'new_value' => $productData['to_location'],
                            'notes' => "To location for product '{$product->name}' changed from {$oldProduct['to_location']} to {$productData['to_location']}"
                        ]);
                    }
                } else {
                    // New product was added
                    TransferOrderHistory::create([
                        'transfer_order_id' => $transferOrder->id,
                        'user_id' => auth()->id(),
                        'action' => 'product_added',
                        'field' => 'product',
                        'change_type' => 'product',
                        'product_id' => $productData['product_id'],
                        'old_value' => null,
                        'new_value' => json_encode([
                            'product_id' => $productData['product_id'],
                            'product_name' => $product->name,
                            'from_location' => $productData['from_location'],
                            'to_location' => $productData['to_location'],
                            'quantity' => $productData['quantity']
                        ]),
                        'notes' => "Product '{$product->name}' added to transfer order"
                    ]);
                }

                // Create the transfer order product
                $transferOrderProduct = $transferOrder->products()->create([
                    'product_id' => $productData['product_id'],
                    'from_location' => $productData['from_location'],
                    'to_location' => $productData['to_location'],
                    'quantity' => $productData['quantity'],
                    // Store historical data at creation
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'stock_available_before' => $stockAvailableBefore,
                    'stock_available_after' => $stockAvailableAfter,
                    'store_available_before' => $storeAvailableBefore,
                    'store_available_after' => $storeAvailableAfter,
                ]);

                $newProductIds[] = $productData['product_id'];
            }

            // Check for removed products
            foreach ($oldProducts as $oldProduct) {
                if (!in_array($oldProduct['product_id'], $newProductIds)) {
                    TransferOrderHistory::create([
                        'transfer_order_id' => $transferOrder->id,
                        'user_id' => auth()->id(),
                        'action' => 'product_removed',
                        'field' => 'product',
                        'change_type' => 'product',
                        'product_id' => $oldProduct['product_id'],
                        'transfer_order_product_id' => $oldProduct['id'],
                        'old_value' => json_encode($oldProduct),
                        'new_value' => null,
                        'notes' => "Product '{$oldProduct['product_name']}' removed from transfer order"
                    ]);
                }
            }

            // Record a general update history
            if (count($newProductIds) > 0) {
                TransferOrderHistory::create([
                    'transfer_order_id' => $transferOrder->id,
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'field' => 'products',
                    'change_type' => 'products',
                    'old_value' => json_encode($oldProducts),
                    'new_value' => json_encode($data['products']),
                    'notes' => 'Transfer order products updated'
                ]);
            }

            DB::commit();

            return $transferOrder->fresh()->load(['products.product', 'histories.user', 'histories.product']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }   public function completeOrder($id)
{
    $transferOrder = $this->findOrFail($id);

    if (!$transferOrder->can_complete) {
        throw new \Exception('Transfer order cannot be completed');
    }

    DB::beginTransaction();

    try {
        // Update product stock and create stock adjustments
        foreach ($transferOrder->products as $transferProduct) {
            $product = $transferProduct->product;

            // Check stock availability
            if ($transferProduct->from_location === 'stock_available') {
                if ($product->stock_available < $transferProduct->quantity) {
                    throw new \Exception("Insufficient stock in stock_available for product: {$product->name}");
                }
            } else {
                if ($product->store_available < $transferProduct->quantity) {
                    throw new \Exception("Insufficient stock in store_available for product: {$product->name}");
                }
            }

            // Update product stock WITHOUT stock adjustments
            if ($transferProduct->from_location === 'stock_available') {
                $product->stock_available -= $transferProduct->quantity;
            } else {
                $product->store_available -= $transferProduct->quantity;
            }

            if ($transferProduct->to_location === 'stock_available') {
                $product->stock_available += $transferProduct->quantity;
            } else {
                $product->store_available += $transferProduct->quantity;
            }

            $product->save();
        }

        // Mark transfer order as completed
        $transferOrder->markAsCompleted(auth()->id());

        // Record history
        TransferOrderHistory::create([
            'transfer_order_id' => $transferOrder->id,
            'user_id' => auth()->id(),
            'action' => 'status_changed',
            'field' => 'status',
            'old_value' => 'PENDING',
            'new_value' => 'COMPLETED',
            'notes' => 'Transfer order completed'
        ]);

        DB::commit();

        return $transferOrder->load(['products.product', 'approvedBy']);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
    public function cancelOrder($id)
    {
        $transferOrder = $this->findOrFail($id);

        if (!$transferOrder->can_cancel) {
            throw new \Exception('Transfer order cannot be canceled');
        }

        DB::beginTransaction();

        try {
            // Mark as canceled
            $transferOrder->markAsCanceled(auth()->id());

            // Record history
            TransferOrderHistory::create([
                'transfer_order_id' => $transferOrder->id,
                'user_id' => auth()->id(),
                'action' => 'status_changed',
                'field' => 'status',
                'old_value' => 'PENDING',
                'new_value' => 'CANCELED',
                'notes' => 'Transfer order canceled'
            ]);

            DB::commit();

            return $transferOrder->load(['approvedBy']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStatistics($userId = null, $startDate = null, $endDate = null)
    {
        $query = TransferOrder::query();

        if ($userId) {
            $query->where('created_by', $userId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [
            'total' => $query->count(),
            'pending' => $query->where('status', 'PENDING')->count(),
            'completed' => $query->where('status', 'COMPLETED')->count(),
            'canceled' => $query->where('status', 'CANCELED')->count(),
            'total_products' => $query->with('products')->get()->sum(function ($order) {
                return $order->products->count();
            }),
            'total_quantity' => $query->with('products')->get()->sum(function ($order) {
                return $order->products->sum('quantity');
            })
        ];
    }
}
