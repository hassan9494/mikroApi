<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\TransferOrder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ShelfFillingController extends Controller
{
    use ApiResponser;

    /**
     * List products where store_available < min_capacity
     */
    public function index(Request $request)
    {
        if (!Gate::allows('shelf_filling_list_view')) {
            abort(403);
        }

        $filterMode = $request->get('filter_mode', 'below_min');

        $query = Product::query();

        if ($filterMode === 'below_min') {
            // Only products where store qty is below min capacity
            $query->where('min_capacity', '>', 0)
                ->whereColumn('store_available', '<', 'min_capacity')
                ->where('stock_available', '>', 0);
        }
        // filter_mode=all -> show all products (so user can set min/max capacity)

        // Search by name or SKU
        if ($request->filled('search') && $request->search !== 'undefined') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('limit', 10);
        $products = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = $products->getCollection()->map(function ($product) {
            return $this->buildShelfFillingData($product);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Create a transfer order from selected products
     */
    public function createTransfer(Request $request)
    {
        if (!Gate::allows('shelf_filling_create_transfer')) {
            abort(403);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:products,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $transferOrder = TransferOrder::create([
                'notes' => trim(($validated['notes'] ?? '') . ' (Shelf Filling)'),
                'created_by' => auth()->id(),
                'status' => 'PENDING',
            ]);

            $productsAdded = 0;
            $skippedProducts = [];

            foreach ($validated['product_ids'] as $productId) {
                $product = Product::find($productId);

                if (!$product || $product->min_capacity <= 0) {
                    $skippedProducts[] = $productId;
                    continue;
                }

                $storeAvailable = (int) $product->store_available;
                $stockAvailable = (int) $product->stock_available;
                $maxCapacity = (int) $product->max_capacity;

                // Skip if store is already at or above min_capacity
                if ($storeAvailable >= $product->min_capacity) {
                    $skippedProducts[] = $productId;
                    continue;
                }

                // Calculate transfer qty
                $needed = $maxCapacity - $storeAvailable;
                $transferQty = min($needed, $stockAvailable);

                // Skip if nothing to transfer
                if ($transferQty <= 0) {
                    $skippedProducts[] = $productId;
                    continue;
                }

                // Capture current stock values (BEFORE)
                $stockBefore = $product->stock_available + $product->store_available;
                $stockAvailableBefore = $product->stock_available;
                $storeAvailableBefore = $product->store_available;

                // Calculate expected AFTER values
                $stockAvailableAfter = $stockAvailableBefore - $transferQty;
                $storeAvailableAfter = $storeAvailableBefore + $transferQty;
                $stockAfter = $stockAvailableAfter + $storeAvailableAfter;

                $transferOrder->products()->create([
                    'product_id' => $productId,
                    'from_location' => 'stock_available',
                    'to_location' => 'store_available',
                    'quantity' => $transferQty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'stock_available_before' => $stockAvailableBefore,
                    'stock_available_after' => $stockAvailableAfter,
                    'store_available_before' => $storeAvailableBefore,
                    'store_available_after' => $storeAvailableAfter,
                ]);

                $productsAdded++;
            }

            // If no products were actually added, rollback
            if ($productsAdded === 0) {
                DB::rollBack();
                return $this->error(
                    'No products could be added to the transfer order. All selected products either have sufficient stock on shelf or no warehouse stock available.',
                    422
                );
            }

            DB::commit();

            return $this->success(
                [
                    'transfer_order_id' => $transferOrder->id,
                    'transfer_order_number' => $transferOrder->number,
                    'products_added' => $productsAdded,
                    'products_skipped' => count($skippedProducts),
                ],
                'Transfer order created successfully from shelf filling',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create transfer order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export shelf filling report as Excel
     */
    public function export(Request $request)
    {
        if (!Gate::allows('shelf_filling_export')) {
            abort(403);
        }

        $query = Product::where('min_capacity', '>', 0)
            ->whereColumn('store_available', '<', 'min_capacity');

        if ($request->filled('search') && $request->search !== 'undefined') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Shelf Filling');

        $headers = [
            'SKU',
            'Name',
            'Store Qty',
            'Stock Qty',
            'Min Capacity',
            'Max Capacity',
            'Transfer Qty',
            'Status',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($products as $product) {
            $data = $this->buildShelfFillingData($product);

            $statusLabel = match ($data['status']) {
            'no_stock' => 'No Stock',
                'partial' => 'Partial',
                'sufficient' => 'Sufficient',
                default => $data['status'],
            };

            $sheet->fromArray([
                $product->sku,
                $product->name,
                $data['store_available'],
                $data['stock_available'],
                $data['min_capacity'],
                $data['max_capacity'],
                $data['transfer_qty'],
                $statusLabel,
            ], null, "A{$row}");

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Center align data cells
        if ($row > 2) {
            $sheet->getStyle('A2:H' . ($row - 1))
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        $fileName = 'shelf_filling_' . date('Y-m-d_H-i-s') . '.xlsx';
        $tempPath = storage_path('app/exports/' . $fileName);

        // Ensure directory exists
        if (!is_dir(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Bulk update min_capacity and max_capacity for products
     */
    public function saveCapacity(Request $request)
    {
        if (!Gate::allows('shelf_filling_list_view')) {
            abort(403);
        }

        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.min_capacity' => 'nullable|integer|min:0',
            'products.*.max_capacity' => 'nullable|integer|min:0',
        ]);

        foreach ($validated['products'] as $product) {
            Product::where('id', $product['id'])->update(
                array_filter([
                    'min_capacity' => $product['min_capacity'] ?? null,
                    'max_capacity' => $product['max_capacity'] ?? null,
                ], fn($v) => !is_null($v))
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Capacity values updated successfully'
        ]);
    }

    /**
     * Build shelf filling calculation data for a product
     */
    private function buildShelfFillingData(Product $product): array
    {
        $storeAvailable = (int) $product->store_available;
        $stockAvailable = (int) $product->stock_available;
        $minCapacity = (int) $product->min_capacity;
        $maxCapacity = (int) $product->max_capacity;

        $needed = $maxCapacity - $storeAvailable;
        $transferQty = ($stockAvailable >= $needed) ? $needed : $stockAvailable;

        if ($stockAvailable <= 0) {
            $transferQty = 0;
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'image' => $product->getFirstMediaUrl() ? asset($product->getFirstMediaUrl()) : null,
            'store_available' => $storeAvailable,
            'stock_available' => $stockAvailable,
            'min_capacity' => $minCapacity,
            'max_capacity' => $maxCapacity,
            'transfer_qty' => $transferQty,
            'has_sufficient_stock' => $stockAvailable >= $needed,
            'status' => $stockAvailable <= 0 ? 'no_stock'
                : ($stockAvailable >= $needed ? 'sufficient' : 'partial'),
            'store_location' => $product->location,
            'stock_location' => $product->stock_location,
        ];
    }
}
