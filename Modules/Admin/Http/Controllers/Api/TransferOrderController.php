<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Admin\Http\Resources\TransferOrderResource;
use App\Models\TransferOrder;
use App\Models\TransferOrderProduct;
use App\Models\TransferOrderHistory;

class TransferOrderController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        if (!Gate::allows('transfer_order_list_view')) {
            abort(403, 'Unauthorized');
        }

        $query = TransferOrder::with(['createdBy', 'approvedBy', 'products.product'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all' && $request->status !== 'undefined') {
            $validStatuses = ['PENDING', 'COMPLETED'];
            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Search
        if ($request->has('search') && $request->search && $request->search !== 'undefined') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('createdBy', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('products.product', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Date range filter
        if ($request->filled('start_date') && $request->start_date !== 'undefined') {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date') && $request->end_date !== 'undefined') {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 20);
        $transferOrders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => TransferOrderResource::collection($transferOrders),
                'total' => $transferOrders->total(),
                'per_page' => $transferOrders->perPage(),
                'current_page' => $transferOrders->currentPage(),
                'last_page' => $transferOrders->lastPage()
            ]
        ]);
    }

    public function store(Request $request)
    {
        if (!Gate::allows('transfer_order_create')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.from_location' => 'required|in:stock_available,store_available',
            'products.*.to_location' => 'required|in:stock_available,store_available',
            'products.*.quantity' => 'required|integer|min:1|max:9999'
        ]);

        DB::beginTransaction();

        try {
            // Create transfer order
            $transferOrder = TransferOrder::create([
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'PENDING'
            ]);

            // Add products with CURRENT stock values and CALCULATED after values
            foreach ($validated['products'] as $productData) {
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

                $transferOrder->products()->create([
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
            }

            // Record history
            $transferOrder->histories()->create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'notes' => 'Transfer order created'
            ]);

            DB::commit();

            return $this->success(
                new TransferOrderResource($transferOrder->load(['products.product', 'createdBy'])),
                'Transfer order created successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create transfer order: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $transferOrder = TransferOrder::with([
            'products.product',
            'createdBy',
            'approvedBy',
            'histories.user',
            'histories.product'
        ])->findOrFail($id);

        if (!Gate::allows('transfer_order_list_view')) {
            abort(403, 'Unauthorized');
        }

        return $this->success(new TransferOrderResource($transferOrder));
    }
    public function update(Request $request, $id)
    {
        if (!Gate::allows('transfer_order_edit')) {
            abort(403, 'Unauthorized');
        }

        $transferOrder = TransferOrder::with(['products.product'])->findOrFail($id);

        if (!$transferOrder->can_edit) {
            return $this->error('Cannot edit completed transfer order', 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.from_location' => 'required|in:stock_available,store_available',
            'products.*.to_location' => 'required|in:stock_available,store_available',
            'products.*.quantity' => 'required|integer|min:1|max:9999'
        ]);

        DB::beginTransaction();

        try {
            // Store current (old) products BEFORE making changes
            $oldProducts = $transferOrder->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_id' => $product->product_id,
                    'product_name' => $product->product->name ?? 'Unknown',
                    'from_location' => $product->from_location,
                    'to_location' => $product->to_location,
                    'quantity' => $product->quantity
                ];
            })->toArray();

            // Update transfer order notes
            $oldNotes = $transferOrder->notes;
            $transferOrder->update([
                'notes' => $validated['notes'] ?? null
            ]);

            // Record history if notes changed
            if ($oldNotes != $transferOrder->notes) {
                $transferOrder->histories()->create([
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'field' => 'notes',
                    'change_type' => 'notes',
                    'old_value' => $oldNotes,
                    'new_value' => $transferOrder->notes,
                    'notes' => 'Notes updated'
                ]);
            }

            // Get new products from request
            $newProducts = $validated['products'];
            $newProductIds = array_column($newProducts, 'product_id');

            // 1. Check for REMOVED products
            $oldProductIds = array_column($oldProducts, 'product_id');
            $removedProductIds = array_diff($oldProductIds, $newProductIds);

            foreach ($removedProductIds as $removedId) {
                $removedProduct = collect($oldProducts)->firstWhere('product_id', $removedId);
                if ($removedProduct) {
                    $transferOrder->histories()->create([
                        'user_id' => auth()->id(),
                        'action' => 'product_removed',
                        'field' => 'product',
                        'change_type' => 'product',
                        'product_id' => $removedId,
                        'old_value' => json_encode([
                            'product_id' => $removedProduct['product_id'],
                            'product_name' => $removedProduct['product_name'],
                            'from_location' => $removedProduct['from_location'],
                            'to_location' => $removedProduct['to_location'],
                            'quantity' => $removedProduct['quantity']
                        ]),
                        'new_value' => null,
                        'notes' => "Product '{$removedProduct['product_name']}' removed from transfer order"
                    ]);
                }
            }

            // 2. Delete existing products
            $transferOrder->products()->delete();

            // 3. Add new products WITH DETAILED CHANGE TRACKING
            foreach ($newProducts as $productData) {
                $product = \Modules\Shop\Entities\Product::find($productData['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found: {$productData['product_id']}");
                }

                // Check if this product existed in old products
                $oldProduct = collect($oldProducts)->firstWhere('product_id', $productData['product_id']);

                if ($oldProduct) {
                    // Product existed - check for specific changes
                    $changes = [];

                    // Check quantity change
                    if ($oldProduct['quantity'] != $productData['quantity']) {
                        $transferOrder->histories()->create([
                            'user_id' => auth()->id(),
                            'action' => 'quantity_changed',
                            'field' => 'quantity',
                            'change_type' => 'quantity',
                            'product_id' => $productData['product_id'],
                            'old_value' => $oldProduct['quantity'],
                            'new_value' => $productData['quantity'],
                            'notes' => "Quantity for product '{$product->name}' changed from {$oldProduct['quantity']} to {$productData['quantity']}"
                        ]);
                    }

                    // Check from_location change
                    if ($oldProduct['from_location'] != $productData['from_location']) {
                        $transferOrder->histories()->create([
                            'user_id' => auth()->id(),
                            'action' => 'from_location_changed',
                            'field' => 'from_location',
                            'change_type' => 'from_location',
                            'product_id' => $productData['product_id'],
                            'old_value' => $oldProduct['from_location'],
                            'new_value' => $productData['from_location'],
                            'notes' => "From location for product '{$product->name}' changed from {$oldProduct['from_location']} to {$productData['from_location']}"
                        ]);
                    }

                    // Check to_location change
                    if ($oldProduct['to_location'] != $productData['to_location']) {
                        $transferOrder->histories()->create([
                            'user_id' => auth()->id(),
                            'action' => 'to_location_changed',
                            'field' => 'to_location',
                            'change_type' => 'to_location',
                            'product_id' => $productData['product_id'],
                            'old_value' => $oldProduct['to_location'],
                            'new_value' => $productData['to_location'],
                            'notes' => "To location for product '{$product->name}' changed from {$oldProduct['to_location']} to {$productData['to_location']}"
                        ]);
                    }
                } else {
                    // NEW product was added
                    $transferOrder->histories()->create([
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

                // Capture current stock values
                $stockBefore = $product->stock_available + $product->store_available;
                $stockAvailableBefore = $product->stock_available;
                $storeAvailableBefore = $product->store_available;

                // Calculate expected AFTER values
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

                // Create the transfer order product
                $transferOrder->products()->create([
                    'product_id' => $productData['product_id'],
                    'from_location' => $productData['from_location'],
                    'to_location' => $productData['to_location'],
                    'quantity' => $productData['quantity'],

                    // Store historical data
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'stock_available_before' => $stockAvailableBefore,
                    'stock_available_after' => $stockAvailableAfter,
                    'store_available_before' => $storeAvailableBefore,
                    'store_available_after' => $storeAvailableAfter,
                ]);
            }

            DB::commit();

            return $this->success(
                new TransferOrderResource($transferOrder->fresh()->load(['products.product', 'histories.user', 'histories.product'])),
                'Transfer order updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update transfer order: ' . $e->getMessage(), 500);
        }
    }
    // New method to toggle between PENDING and COMPLETED
    public function toggleStatus($id)
    {
        if (!Gate::allows('transfer_order_complete')) {
            abort(403, 'Unauthorized');
        }

        $transferOrder = TransferOrder::with(['products.product'])->findOrFail($id);

        DB::beginTransaction();

        try {
            $oldStatus = $transferOrder->status;

            if ($oldStatus === 'PENDING') {
                // Validate stock availability before completing
                foreach ($transferOrder->products as $transferProduct) {
                    $product = $transferProduct->product;

                    // Store historical data BEFORE transfer
                    $stockBefore = $product->stock_available + $product->store_available;
                    $stockAvailableBefore = $product->stock_available;
                    $storeAvailableBefore = $product->store_available;

                    // Check stock availability
                    if ($transferProduct->from_location === 'stock_available') {
                        if ($product->stock_available < $transferProduct->quantity) {
                            throw new \Exception("Insufficient stock in stock_available for product: {$product->name}. Available: {$product->stock_available}, Requested: {$transferProduct->quantity}");
                        }
                    } else {
                        if ($product->store_available < $transferProduct->quantity) {
                            throw new \Exception("Insufficient stock in store_available for product: {$product->name}. Available: {$product->store_available}, Requested: {$transferProduct->quantity}");
                        }
                    }

                    // Update product stock
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

                    // Calculate after values
                    $stockAfter = $product->stock_available + $product->store_available;
                    $stockAvailableAfter = $product->stock_available;
                    $storeAvailableAfter = $product->store_available;

                    // Update transfer order product with historical data
                    $transferProduct->update([
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'stock_available_before' => $stockAvailableBefore,
                        'stock_available_after' => $stockAvailableAfter,
                        'store_available_before' => $storeAvailableBefore,
                        'store_available_after' => $storeAvailableAfter,
                    ]);
                }

                // Mark as completed
                $newStatus = 'COMPLETED';
                $transferOrder->update([
                    'status' => 'COMPLETED',
                    'approved_by' => auth()->id(),
                    'completed_at' => now()
                ]);

            } else { // COMPLETED -> PENDING
                // Reverse the stock transfer
                foreach ($transferOrder->products as $transferProduct) {
                    $product = $transferProduct->product;

                    // Reverse the transfer: move from to_location back to from_location
                    if ($transferProduct->to_location === 'stock_available') {
                        if ($product->stock_available < $transferProduct->quantity) {
                            throw new \Exception("Insufficient stock in stock_available to revert for product: {$product->name}");
                        }
                        $product->stock_available -= $transferProduct->quantity;
                    } else {
                        if ($product->store_available < $transferProduct->quantity) {
                            throw new \Exception("Insufficient stock in store_available to revert for product: {$product->name}");
                        }
                        $product->store_available -= $transferProduct->quantity;
                    }

                    // Add back to from_location
                    if ($transferProduct->from_location === 'stock_available') {
                        $product->stock_available += $transferProduct->quantity;
                    } else {
                        $product->store_available += $transferProduct->quantity;
                    }

                    $product->save();
                }

                $newStatus = 'PENDING';
                $transferOrder->update([
                    'status' => 'PENDING',
                    'approved_by' => null,
                    'completed_at' => null
                ]);
            }

            // Record history
            // In the toggleStatus method - fix the history creation
            $transferOrder->histories()->create([
                'user_id' => auth()->id(),
                'action' => 'status_changed',
                'field' => 'status',
                'change_type' => 'status', // ADD THIS LINE
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'notes' => $newStatus === 'COMPLETED' ? 'Transfer order completed' : 'Transfer order reverted to pending'
            ]);

            DB::commit();

            return $this->success(
                new TransferOrderResource($transferOrder->fresh()->load(['products.product', 'approvedBy'])),
                $newStatus === 'COMPLETED' ? 'Transfer order completed successfully' : 'Transfer order reverted to pending successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update transfer order status: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        if (!Gate::allows('transfer_order_delete')) {
            abort(403, 'Unauthorized');
        }

        $transferOrder = TransferOrder::findOrFail($id);

        if (!$transferOrder->can_edit) {
            return $this->error('Cannot delete completed transfer order', 400);
        }

        DB::beginTransaction();

        try {
            // Record history before deletion
            $transferOrder->histories()->create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'notes' => 'Transfer order deleted'
            ]);

            // Delete related records
            $transferOrder->products()->delete();
            $transferOrder->histories()->delete();

            // Delete transfer order
            $transferOrder->delete();

            DB::commit();

            return $this->success([], 'Transfer order deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete transfer order: ' . $e->getMessage(), 500);
        }
    }

    public function statistics()
    {
        if (!Gate::allows('transfer_order_list_view')) {
            abort(403, 'Unauthorized');
        }

        $total = TransferOrder::count();
        $pending = TransferOrder::pending()->count();
        $completed = TransferOrder::completed()->count();
        $today = TransferOrder::today()->count();
        $thisWeek = TransferOrder::thisWeek()->count();
        $thisMonth = TransferOrder::thisMonth()->count();

        return $this->success([
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'completed' => $completed,
                'today' => $today,
                'this_week' => $thisWeek,
                'this_month' => $thisMonth
            ]
        ]);
    }
}
