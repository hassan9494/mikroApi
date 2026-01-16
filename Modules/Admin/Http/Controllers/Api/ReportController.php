<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Http\Resources\CustomsStatementResource;
use Modules\Admin\Http\Resources\DeptResource;
use Modules\Admin\Http\Resources\NeedStocksReportResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Resources\OutlayResource;
use Modules\Admin\Http\Resources\ProductSalesReportResource;
use Modules\Admin\Http\Resources\ProductStocksReportResource;
use Modules\Admin\Http\Resources\ReturnOrderResource;
use Modules\Admin\Http\Resources\TransactionResource;
use Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface;
use Modules\Common\Repositories\Dept\DeptRepositoryInterface;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\ReturnOrder\ReturnOrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepository;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Modules\Shop\Repositories\Transaction\TransactionRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use ZipArchive;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    use ApiResponser;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepositoryInterface;
    /**
     * @var OutlayRepositoryInterface
     */
    private OutlayRepositoryInterface $outlayRepositoryInterface;


    /**
     * @var CustomsStatementRepositoryInterface
     */
    private CustomsStatementRepositoryInterface $customsStatementRepositoryInterface;


    /**
     * @var DeptRepositoryInterface
     */
    private DeptRepositoryInterface $deptRepositoryInterface;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private TransactionRepositoryInterface $transactionRepository;
    /**
     * @var ReturnOrderRepositoryInterface
     */
    private ReturnOrderRepositoryInterface $returnOrderRepository;
    private ProductRepository $pr;

    /**
     * ReportController constructor.
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param OrderRepositoryInterface $orderRepository
     * @param ReturnOrderRepositoryInterface $returnOrderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OutlayRepositoryInterface $outlayRepositoryInterface
     * @param DeptRepositoryInterface $deptRepositoryInterface
     * @param CustomsStatementRepositoryInterface $customsStatementRepositoryInterface
     */
    public function __construct(
        ProductRepositoryInterface $productRepositoryInterface,
        OrderRepositoryInterface $orderRepository,
        ReturnOrderRepositoryInterface $returnOrderRepository,
        OutlayRepositoryInterface $outlayRepositoryInterface,
        DeptRepositoryInterface $deptRepositoryInterface,
        CustomsStatementRepositoryInterface $customsStatementRepositoryInterface,
        TransactionRepositoryInterface $transactionRepository,
        ProductRepository $pr
    )
    {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->orderRepository = $orderRepository;
        $this->returnOrderRepository = $returnOrderRepository;
        $this->outlayRepositoryInterface = $outlayRepositoryInterface;
        $this->deptRepositoryInterface = $deptRepositoryInterface;
        $this->transactionRepository = $transactionRepository;
        $this->customsStatementRepositoryInterface = $customsStatementRepositoryInterface;
        $this->pr = $pr;
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function order()
    {
        ini_set('memory_limit', '1024M');
        $where = [
            [
                'taxed_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'taxed_at', '<=', request('newTo', now()->endOfMonth())
            ],
            [
                'options->taxed', true
            ]
        ];
        if ($exempt = request('exempt')) {
            $where[] = ['options->tax_exempt', $exempt];
        }
        if ($zero = request('zero')) {
            $where[] = ['options->tax_exempt', true];
            $where[] = ['options->tax_zero', $zero];
        }
        if ($status = request('status')) {
            $where[] = ['status', $status];
        }
//        dd($orWhere);
        $data = $this->orderRepository->get($where, ['products'])->sortBy('tax_number');
        return OrderResource::collection($data);
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function return_order()
    {

        $where = [
            [
                'created_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'created_at', '<=', request('newTo', now()->endOfMonth())
            ],
            [
                'status', 'COMPLETED'
            ]
        ];
        $status = request('status');
        if ($status == 0 || $status == 1 || $status == "0" || $status == "1" ) {
            $where[] = ['is_migrated', $status];
        }
        $data = $this->returnOrderRepository->get($where, ['products','order'])->sortBy('id');
        return ReturnOrderResource::collection($data);
    }


    public function product()
    {
        $where = [
            [
                'completed_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'completed_at', '<=', request('newTo', now()->endOfMonth())
            ]
        ];
        $data = Product::all();
        return  ProductSalesReportResource::collection($data);
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function products_order()
    {
        $order_where = [
            [
                'taxed_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'taxed_at', '<=', request('to', now()->endOfMonth())
            ],
            [
                'status', 'COMPLETED'
            ]
        ];

        $where = [
            [
                'id', request('id')
            ]
        ];
        $data = $this->productRepositoryInterface->get($where, ['orders'])->first();
        $orders = $data->orders->where('status', 'COMPLETED')
            ->where('taxed_at','>=',request('from', now()->startOfMonth()))
            ->where('taxed_at','<=',request('to', now()->startOfMonth()));
        return OrderResource::collection($orders);
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function zemam()
    {
        // Initialize conditions
        $where = [];
        $orWhere = [];

        // Handle "from" and "newTo" logic
        $from = request('from');
        $newTo = request('newTo');

        if ($from && $newTo) {
            // Both "from" and "newTo" are set
            $where[] = ['taxed_at', '>=', $from];
            $where[] = ['taxed_at', '<=', $newTo];
            $orWhere[] = ['updated_at', '>=', $from];
            $orWhere[] = ['updated_at', '<=', $newTo];
        } elseif ($from) {
            // Only "from" is set
            $where[] = ['taxed_at', '>=', $from];
            $orWhere[] = ['updated_at', '>=', $from];
        } elseif ($newTo) {
            // Only "newTo" is set
            $where[] = ['taxed_at', '<=', $newTo];
            $orWhere[] = ['updated_at', '<=', $newTo];
        }

        // Default case: no "from" or "newTo"
        if (!$from && !$newTo) {
            // Leave conditions empty to fetch all data
        }

        // Handle "dept" logic
        if ($dept = request('dept')) {
            $where[] = ['options->dept', $dept];
            $orWhere[] = ['options->dept', $dept];
        } else {
            $where[] = ['options->dept', true];
            $orWhere[] = ['options->dept', true];
        }

        // Handle "status" logic
        if ($status = request('status')) {
            $where[] = ['status', $status];
            $orWhere[] = ['status', $status];
        }

        // Fetch data using orderRepository
        $data = $this->orderRepository->get($where, ['products'], $orWhere)->sortBy('tax_number');

        return OrderResource::collection($data);
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function transaction()
    {
        // Initialize conditions
        $where = [];

        // Handle "from" and "newTo" logic
        $from = request('from');
        $newTo = request('newTo');

        if ($from && $newTo) {
            // Both "from" and "newTo" are set
            $where[] = ['created_at', '>=', $from];
            $where[] = ['created_at', '<=', $newTo];
        } elseif ($from) {
            // Only "from" is set
            $where[] = ['created_at', '>=', $from];
        } elseif ($newTo) {
            // Only "newTo" is set
            $where[] = ['created_at', '<=', $newTo];
        }

        // Default case: no "from" or "newTo"
        if (!$from && !$newTo) {
            // Leave conditions empty to fetch all data
        }

        // Handle "dept" logic
        if ($type = request('type')) {
            $where[] = ['type', $type];
        } else {
        }

        // Handle "status" logic
        if ($payment_method_id = request('payment_method_id')) {
            $where[] = ['payment_method_id', $payment_method_id];
        }

        // Fetch data using orderRepository
        $data = $this->transactionRepository->get($where, ['order'])->sortBy('id');

        return TransactionResource::collection($data);
    }



    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function delivery()
    {

        $where = [
            [
                'status',  'PROCESSING'
            ],
            [
                'shipping->status',  '!=','WAITING'
            ],
            [
                'shipping->status',  '!=',null
            ]
        ];
        $orWhere = [
            [
                'status',  'PROCESSING'
            ],
            [
                'shipping->status',  '!=','WAITING'
            ],
            [
                'shipping->status',  '!=',null
            ]
        ];
        if ($dept = request('dept')) {
            $where[] = ['options->dept', $dept];
            $orWhere[] = ['options->dept', $dept];
        }
        if ($shippingProviderId = request('provider')) {
            $where[] = ['shipping_provider_id', $shippingProviderId];
            $orWhere[] = ['shipping_provider_id', $shippingProviderId];
        }
        $data = $this->orderRepository->get($where, ['products'],$orWhere)->sortBy('tax_number');
        return OrderResource::collection($data);
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function outlays()
    {
        $where = [
            [
                'date', '>=', request('from', now()->startOfMonth())
            ],
            [
                'date', '<=', request('to', now()->endOfMonth())
            ],
            [
                'type', 'OUTLAY'
            ]
        ];
        if ($sub_type = request('sub_type')) {
            $where[] = ['sub_type', $sub_type];
        }
        $data = $this->outlayRepositoryInterface->get($where)->sortBy('date');
        return OutlayResource::collection($data);
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function purchases()
    {
        $where = [
            [
                'date', '>=', request('from', now()->startOfMonth())
            ],
            [
                'date', '<=', request('to', now()->endOfMonth())
            ],
            [
                'type', 'PURCHASE'
            ]
        ];
        if ($sub_type = request('sub_type')) {
            $where[] = ['sub_type', $sub_type];
        }
        $data = $this->outlayRepositoryInterface->get($where)->sortBy('date');
        return OutlayResource::collection($data);
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function customs_statement()
    {
        $where = [
            [
                'date', '>=', request('from', now()->startOfMonth())
            ],
            [
                'date', '<=', request('to', now()->endOfMonth())
            ]
        ];
        $data = $this->customsStatementRepositoryInterface->get($where)->sortBy('date');
        return CustomsStatementResource::collection($data);
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function depts()
    {
        $where = [
            [
                'date', '>=', request('from', now()->startOfMonth())
            ],
            [
                'date', '<=', request('to', now()->endOfMonth())
            ],
            [
                'paid', request('paid')
            ]
        ];
        $data = $this->deptRepositoryInterface->get($where)->sortBy('date');
        return DeptResource::collection($data);
    }

    /**
     * @return JsonResponse
     */
    public function productSales(): JsonResponse
    {
        $from = request('from');
        $to = request('to');


        return Datatable::make($this->productRepositoryInterface->model())
            ->search('id', 'name', 'sku')
            ->resource(ProductSalesReportResource::class)
            ->additionalData(['from' => $from != 'undefined' ? $from : null, 'to' => $to != 'undefined' ? $to : null])
            ->json();
    }



    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function productStock(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Get request parameters
        $needCondition = request('needConditionReport');
        $sourceType = request('sourceType', null);
        $sourceId = request('source_id', null);

        // Determine the actual condition
        // If needCondition is an array, get first element
        $condition = is_array($needCondition) ? ($needCondition[0] ?? null) : $needCondition;

        // Build query manually (since repository can't handle complex conditions)
        $query = Product::query();

        // Always apply these conditions
        $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit')) = false")
            ->where('hasVariants', false);

        // Handle different condition types
        if ($condition === 'stock') {
            // For 'stock', don't add min_qty conditions
            // Already added common conditions
        } else {
            // For 'need', 'nwaqes', or default
            if ($condition !== 'need') {
                // For 'nwaqes' or default, add is_retired
                $query->where('is_retired', 0);
            }

            // Add min_qty conditions based on sourceType
            $this->addMinQtyConditionsToQuery($query, $sourceType);
        }

        // Add source_id conditions based on sourceType
        $this->addSourceIdConditionsToQuery($query, $sourceType, $sourceId);

        // Get and sort data
        $data = $query->get()->sortBy('date');

        return NeedStocksReportResource::collection($data);
    }

    /**
     * Add min_qty conditions to query based on sourceType
     */
    private function addMinQtyConditionsToQuery($query, $sourceType)
    {
        if ($sourceType) {
            // Specific sourceType
            $minQtyColumn = $sourceType . '_min_qty';
            $query->where($minQtyColumn, '>', 0)
                ->whereRaw("stock < $minQtyColumn");
        } else {
            // No sourceType - check all three columns
            $query->where(function($q) {
                $q->where(function($subQuery) {
                    $subQuery->where('air_min_qty', '>', 0)
                        ->whereRaw('stock < air_min_qty');
                })->orWhere(function($subQuery) {
                    $subQuery->where('sea_min_qty', '>', 0)
                        ->whereRaw('stock < sea_min_qty');
                })->orWhere(function($subQuery) {
                    $subQuery->where('local_min_qty', '>', 0)
                        ->whereRaw('stock < local_min_qty');
                });
            });
        }
    }

    /**
     * Add source_id conditions to query based on sourceType
     */
    private function addSourceIdConditionsToQuery($query, $sourceType, $sourceId)
    {
        if ($sourceId) {
            if ($sourceType) {
                // Specific sourceType
                $sourceIdColumn = $sourceType . '_source_id';
                $query->where($sourceIdColumn, $sourceId);
            } else {
                // No sourceType - check all three columns
                $query->where(function($q) use ($sourceId) {
                    $q->where('air_source_id', $sourceId)
                        ->orWhere('sea_source_id', $sourceId)
                        ->orWhere('local_source_id', $sourceId);
                });
            }
        }
    }



    private function buildConditions()
    {
        try {
            // Get request parameters
            $needCondition = request('needConditionReport');
            $sourceType = request('sourceType', null);
            $sourceId = request('source_id', null);

            // Determine the actual condition (handle array or string)
            $condition = is_array($needCondition) ? ($needCondition[0] ?? null) : $needCondition;

            $where = [];

            // Common conditions for all
            $where[] = ["JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))", '=', 'false'];
            $where[] = ['hasVariants', '=', false];

            // Handle different condition types
            if ($condition === 'stock') {
                // For 'stock', don't add min_qty conditions
                // Already added common conditions
            } else {
                // For 'need', 'nwaqes', or default
                if ($condition !== 'need') {
                    // For 'nwaqes' or default, add is_retired
                    $where[] = ['is_retired', '=', 0];
                }

                // Add min_qty conditions based on sourceType
                $this->addMinQtyConditionsToArray($where, $sourceType);
            }

            // Add source_id conditions based on sourceType
            $this->addSourceIdConditionsToArray($where, $sourceType, $sourceId);

            return $where;

        } catch (\Exception $e) {
            \Log::error('Condition build failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Add min_qty conditions to array based on sourceType
     * Returns serializable array (no closures)
     */
    private function addMinQtyConditionsToArray(&$where, $sourceType)
    {
        if ($sourceType) {
            // Specific sourceType - use the corresponding column
            $minQtyColumn = $sourceType . '_min_qty';
            $where[] = [$minQtyColumn, '>', 0];
            $where[] = ['stock', '<', $minQtyColumn]; // This will be handled as raw SQL
        } else {
            // No sourceType - check ALL THREE columns
            // We need to create OR conditions for all three
            $where[] = ['type' => 'or_min_qty'];
        }
    }

    /**
     * Add source_id conditions to array based on sourceType
     * Returns serializable array (no closures)
     */
    private function addSourceIdConditionsToArray(&$where, $sourceType, $sourceId)
    {
        if ($sourceId) {
            if ($sourceType) {
                // Specific sourceType - use the corresponding column
                $sourceIdColumn = $sourceType . '_source_id';
                $where[] = [$sourceIdColumn, '=', $sourceId];
            } else {
                // No sourceType - check ALL THREE columns
                $where[] = ['type' => 'or_source_id', 'value' => $sourceId];
            }
        }
    }

    public function exportImagesZip()
    {
        try {
            $conditions = $this->buildConditions();
            $chunkSize = 100;

            // Build the query with proper handling of all condition types
            $query = Product::query();

            foreach ($conditions as $condition) {
                // Check for special condition types first
                if (isset($condition['type'])) {
                    if ($condition['type'] === 'or_min_qty') {
                        // No sourceType - check ALL THREE columns
                        $query->where(function($q) {
                            $q->where(function($subQuery) {
                                $subQuery->where('air_min_qty', '>', 0)
                                    ->whereRaw('stock < air_min_qty');
                            })->orWhere(function($subQuery) {
                                $subQuery->where('sea_min_qty', '>', 0)
                                    ->whereRaw('stock < sea_min_qty');
                            })->orWhere(function($subQuery) {
                                $subQuery->where('local_min_qty', '>', 0)
                                    ->whereRaw('stock < local_min_qty');
                            });
                        });
                    }
                    elseif ($condition['type'] === 'or_source_id') {
                        // No sourceType - check ALL THREE source_id columns
                        $query->where(function($q) use ($condition) {
                            $q->where('air_source_id', '=', $condition['value'])
                                ->orWhere('sea_source_id', '=', $condition['value'])
                                ->orWhere('local_source_id', '=', $condition['value']);
                        });
                    }
                }
                elseif (count($condition) === 3) {
                    // Handle normal conditions
                    if (is_string($condition[0]) && str_contains($condition[0], 'JSON_UNQUOTE')) {
                        $query->whereRaw("{$condition[0]} {$condition[1]} ?", [$condition[2]]);
                    }
                    // Handle column comparisons like ['stock', '<', 'air_min_qty']
                    elseif (is_string($condition[0]) && is_string($condition[2])) {
                        $query->whereRaw("{$condition[0]} {$condition[1]} {$condition[2]}");
                    }
                    else {
                        $query->where($condition[0], $condition[1], $condition[2]);
                    }
                }
                elseif (count($condition) === 2) {
                    $query->where($condition[0], $condition[1]);
                }
            }

            $totalProducts = $query->count();
            $totalChunks = ceil($totalProducts / $chunkSize);

            $exportId = 'export_' . time() . '_' . uniqid();
            $exportDir = "exports/{$exportId}";

            if (!Storage::exists($exportDir)) {
                Storage::makeDirectory($exportDir);
            }

            $manifest = [
                'total_chunks' => $totalChunks,
                'chunk_size' => $chunkSize,
                'created_at' => now()->toDateTimeString(),
                'conditions' => $conditions
            ];

            Storage::put("{$exportDir}/manifest.json", json_encode($manifest));

            return response()->json([
                'export_id' => $exportId,
                'total_chunks' => $totalChunks,
                'chunk_size' => $chunkSize,
                'download_base' => url("/api/admin/reports/download-chunk/{$exportId}")
            ]);

        } catch (\Exception $e) {
            \Log::error('Export failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Export failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadChunk($exportId, $chunkIndex)
    {

        $manifest = json_decode(Storage::get("exports/{$exportId}/manifest.json"), true);
        $totalChunks = $manifest['total_chunks'];

        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            abort(404, 'Invalid chunk index');
        }

        $excelPath = "exports/{$exportId}/chunk_{$chunkIndex}.xlsx";

        if (!Storage::exists($excelPath)) {
            $this->generateChunk($exportId, $chunkIndex, $manifest);
        }

        return Storage::download($excelPath, "products_chunk_{$chunkIndex}.xlsx");
    }

    private function generateChunk($exportId, $chunkIndex, $manifest)
    {
        $chunkSize = $manifest['chunk_size'];
        $offset = $chunkIndex * $chunkSize;
        $website = config('app.front_url');

        $query = Product::with(['source', 'media']);

        // Process conditions the same way as exportImagesZip
        foreach ($manifest['conditions'] as $condition) {
            // Check for special condition types first
            if (isset($condition['type'])) {
                if ($condition['type'] === 'or_min_qty') {
                    // No sourceType - check ALL THREE columns
                    $query->where(function($q) {
                        $q->where(function($subQuery) {
                            $subQuery->where('air_min_qty', '>', 0)
                                ->whereRaw('stock < air_min_qty');
                        })->orWhere(function($subQuery) {
                            $subQuery->where('sea_min_qty', '>', 0)
                                ->whereRaw('stock < sea_min_qty');
                        })->orWhere(function($subQuery) {
                            $subQuery->where('local_min_qty', '>', 0)
                                ->whereRaw('stock < local_min_qty');
                        });
                    });
                }
                elseif ($condition['type'] === 'or_source_id') {
                    // No sourceType - check ALL THREE source_id columns
                    $query->where(function($q) use ($condition) {
                        $q->where('air_source_id', '=', $condition['value'])
                            ->orWhere('sea_source_id', '=', $condition['value'])
                            ->orWhere('local_source_id', '=', $condition['value']);
                    });
                }
            }
            elseif (count($condition) === 3) {
                // Handle normal conditions
                if (is_string($condition[0]) && str_contains($condition[0], 'JSON_UNQUOTE')) {
                    $query->whereRaw("{$condition[0]} {$condition[1]} ?", [$condition[2]]);
                }
                // Handle column comparisons like ['stock', '<', 'air_min_qty']
                elseif (is_string($condition[0]) && is_string($condition[2])) {
                    $query->whereRaw("{$condition[0]} {$condition[1]} {$condition[2]}");
                }
                else {
                    $query->where($condition[0], $condition[1], $condition[2]);
                }
            }
            elseif (count($condition) === 2) {
                $query->where($condition[0], $condition[1]);
            }
        }

        $products = $query->offset($offset)
            ->limit($chunkSize)
            ->get();

        $exportDir = storage_path("app/exports/{$exportId}");
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers to match your needsToExcel structure
        $headers = [
            'Id',
            'Image',
            'NAME',
            'Stock',
            'Price',
            'Real_Price',
            'Min_Quantity',
            'Air_Min_Quantity',
            'Sea_Min_Quantity',
            'Local_Min_Quantity',
            'Order_Quantity',
            'Air_Order_Quantity',
            'Sea_Order_Quantity',
            'Local_Order_Quantity',
            'Purchases_Quantity',
            'PriceAll',
            'Real_Price_All',
            'Source_Sku',
            'Air_Source_Sku',
            'Sea_Source_Sku',
            'Local_Source_Sku',
            'Stock_Location',
            'Store_Location',
            'Link',
            'source',
            'Air_source',
            'Sea_source',
            'Local_source'
        ];

        $sheet->fromArray($headers, null, 'A1');

        // Set row height for image rows
        $sheet->getDefaultRowDimension()->setRowHeight(80);

        // Add products
        $row = 2;
        foreach ($products as $product) {
            // Calculate prices
            $price = $product->price->normal_price ?? 0;
            $realPrice = $product->price->real_price ?? 0;
            $stock = $product->stock ?? 0;

            $priceAll = $stock * $price;
            $realPriceAll = $stock * $realPrice;

            // Use sales for purchases quantity as in your resource
            $purchasesQty = $product->sales(null, null) ?? 0;

            // Add product data - matching your frontend structure
            $sheet->fromArray([
                $product->id,
                '', // Placeholder for image
                $product->name,
                $stock == 0 ? "0" : $stock,
                $price == 0 ? "0" : $price,
                $realPrice == 0 ? "0" : $realPrice,
                $product->min_qty == 0 ? "0" : $product->min_qty,
                $product->air_min_qty == 0 ? "0" : $product->air_min_qty,
                $product->sea_min_qty == 0 ? "0" : $product->sea_min_qty,
                $product->local_min_qty == 0 ? "0" : $product->local_min_qty,
                $product->order_qty == 0 ? "0" : $product->order_qty,
                $product->air_order_qty == 0 ? "0" : $product->air_order_qty,
                $product->sea_order_qty == 0 ? "0" : $product->sea_order_qty,
                $product->local_order_qty == 0 ? "0" : $product->local_order_qty,
                $product->purchases_qty == 0 ? "0" : $product->purchases_qty,
                $priceAll == 0 ? "0" : $priceAll,
                $realPriceAll == 0 ? "0" : $realPriceAll,
                $product->source_sku,
                $product->air_source_sku,
                $product->sea_source_sku,
                $product->local_source_sku,
                $product->stock_location,
                $product->location,
                "{$website}/product/{$product->sku}",
                $product->source ? $product->source->name : '',
                $product->airSource ? $product->airSource->name : '',
                $product->seaSource ? $product->seaSource->name : '',
                $product->localSource ? $product->localSource->name : ''
            ], null, "A{$row}");

            // Add image as the second column (Column B)
            if ($product->media->isNotEmpty()) {
                $media = $product->media->first();
                $imagePath = $media->getPath();

                if ($imagePath && file_exists($imagePath)) {
                    $drawing = new Drawing();
                    $drawing->setName("Product_{$product->id}");
                    $drawing->setDescription($product->name);
                    $drawing->setPath($imagePath);
                    $drawing->setHeight(70);
                    $drawing->setWidth(70);
                    $drawing->setCoordinates("B{$row}"); // Column B
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                }
            }

            $row++;
        }

        // Auto-size columns
        foreach(range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM]]
        ];
        $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);

        // Center align all cells
        $sheet->getStyle('A2:N'.($row-1))
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Save Excel file
        $writer = new Xlsx($spreadsheet);
        $excelPath = storage_path("app/exports/{$exportId}/chunk_{$chunkIndex}.xlsx");
        $writer->save($excelPath);
    }

    public function downloadAllChunks($exportId)
    {
        $manifest = json_decode(Storage::get("exports/{$exportId}/manifest.json"), true);
        $zipPath = storage_path("app/exports/{$exportId}/all_products.zip");

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Generate any missing chunks first
        for ($i = 0; $i < $manifest['total_chunks']; $i++) {
            $excelPath = "exports/{$exportId}/chunk_{$i}.xlsx";

            if (!Storage::exists($excelPath)) {
                $this->generateChunk($exportId, $i, $manifest);
            }

            $zip->addFile(
                storage_path("app/{$excelPath}"),
                "products_chunk_{$i}.xlsx"
            );
        }

        $zip->close();

        return response()->download($zipPath, "all_products_{$exportId}.zip")
            ->deleteFileAfterSend(true);
    }

    /**
     * @return JsonResponse
     */
    public function productNeed(): JsonResponse
    {
//        dd(request('conditions'));
        if (request('conditions') != null && request('conditions')[1] == 'nawakes') {
            $where = [
                [
                    'min_qty', '>', 0
                ],
                [
                    'stock', '<', "min_qty"
                ]
            ];
        }

        return Datatable::make($this->productRepositoryInterface->model())
            ->search('id', 'name', 'sku', 'stock', 'min_qty')
            ->resource(NeedStocksReportResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function productSale(): JsonResponse
    {
        $from = request('from');
        $to = request('to');
        $whereHas['proccessingAndCompletedOrders'] = function ($q) use ($from, $to) {
            if ($from) $q->whereDate('inspection_date', '>=', $from);
            if ($to) $q->whereDate('inspection_date', '<=', $to);
        };
        $data = $this->productRepositoryInterface->model();
        return Datatable::make($data)
            ->whereHas($whereHas)
            ->search('id', 'name', 'sku', 'stock', 'min_qty')
            ->resource(ProductSalesReportResource::class)
            ->json();
    }

    public function purchasesByProduct(Request $request)
    {
        try {
            $query = DB::table('invoice_products as ip')
                ->join('products as p', 'ip.product_id', '=', 'p.id')
                ->join('invoices as i', 'ip.invoice_id', '=', 'i.id')
                ->select(
                    'p.id as product_id',
                    'p.name as product_name',
                    'p.sku as product_sku',
                    'p.image as product_image',
                    DB::raw('SUM(ip.quantity) as total_quantity'),
                    DB::raw('AVG(ip.purchases_price) as avg_purchase_price'),
                    DB::raw('SUM(ip.quantity * ip.purchases_price) as total_amount'),
                    DB::raw('COUNT(DISTINCT ip.invoice_id) as invoices_count'),
                    DB::raw('MAX(i.date) as latest_invoice_date'),
                    DB::raw('MIN(i.date) as first_invoice_date'),
                    DB::raw('COUNT(DISTINCT YEAR(i.date), MONTH(i.date)) as purchase_months')
                )
                ->where('i.status', 'COMPLETED')
                ->groupBy('p.id', 'p.name', 'p.sku', 'p.image');

            // FIXED: Proper date filtering using invoice date
            if ($request->has('from') && !empty($request->from) && $request->from !== 'undefined') {
                $query->whereDate('i.date', '>=', $request->from);
            }

            if ($request->has('to') && !empty($request->to) && $request->to !== 'undefined') {
                $query->whereDate('i.date', '<=', $request->to);
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('p.name', 'like', "%{$search}%")
                        ->orWhere('p.sku', 'like', "%{$search}%");
                });
            }

            // Get total count before pagination
            $totalQuery = clone $query;
            $total = count($totalQuery->get());

            // Force the order by latest_invoice_date DESC
            $query->orderBy('latest_invoice_date', 'DESC');

            // Pagination
            $page = $request->get('page', 0);
            $limit = $request->get('limit', 10);
            $offset = $page * $limit;

            $items = $query->offset($offset)->limit($limit)->get();

            // Log the results for debugging
            \Log::info('Purchases report results count: ' . count($items));
            if (count($items) > 0) {
                \Log::info('First item dates - Latest: ' . $items[0]->latest_invoice_date . ', First: ' . $items[0]->first_invoice_date);
            }

            $items->transform(function ($item) {
                $product = Product::with('media')->find($item->product_id);

                if ($product && $product->media->isNotEmpty()) {
                    // This is how your sales report gets the image
                    $item->product_image = $product->getFirstMediaUrl('default');
                } else {
                    $item->product_image = null;
                }

                return $item;
            });

            return response()->json([
                'data' => [
                    'items' => $items,
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in purchasesByProduct: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Error fetching purchases data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function productPurchases(Request $request)
    {
        try {
            $query = DB::table('invoice_products as ip')
                ->join('invoices as i', 'ip.invoice_id', '=', 'i.id')
                ->select(
                    'i.id as invoice_id',
                    'i.number as invoice_number',
                    'i.date as invoice_date',
                    'i.name as invoice_name',
                    'i.status as invoice_status',
                    'i.source_id',
                    'ip.quantity',
                    'ip.purchases_price',
                    DB::raw('ip.quantity * ip.purchases_price as total_amount'),
                    'ip.product_name',
                    'ip.product_id'
                )
                ->where('i.status', 'COMPLETED');

            if ($request->has('product_id') && !empty($request->product_id) && $request->product_id !== 'undefined') {
                $query->where('ip.product_id', $request->product_id);
            }

            // FIXED: Proper date filtering using invoice date
            if ($request->has('from') && !empty($request->from) && $request->from !== 'undefined') {
                $query->whereDate('i.date', '>=', $request->from);
            }

            if ($request->has('to') && !empty($request->to) && $request->to !== 'undefined') {
                $query->whereDate('i.date', '<=', $request->to);
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('i.number', 'like', "%{$search}%")
                        ->orWhere('i.name', 'like', "%{$search}%");
                });
            }

            // FIXED ORDERING: Always order by date descending, then by invoice_id as fallback
            $query->orderBy('i.date', 'desc')->orderBy('i.id', 'desc');

            // Pagination
            $page = $request->get('page', 0);
            $limit = $request->get('limit', 10);
            $offset = $page * $limit;

            $total = $query->count();

            $items = $query->offset($offset)->limit($limit)->get();

            return response()->json([
                'data' => [
                    'items' => $items,
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching product purchases data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
