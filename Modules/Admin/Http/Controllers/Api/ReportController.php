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
use Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface;
use Modules\Common\Repositories\Dept\DeptRepositoryInterface;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\ReturnOrder\ReturnOrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepository;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use ZipArchive;

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
     * @var ReturnOrderRepositoryInterface
     */
    private ReturnOrderRepositoryInterface $returnOrderRepository;
    private ProductRepository $pr;

    /**
     * ReportController constructor.
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param OrderRepositoryInterface $orderRepository
     * @param ReturnOrderRepositoryInterface $returnOrderRepository
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
        ProductRepository $pr
    )
    {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->orderRepository = $orderRepository;
        $this->returnOrderRepository = $returnOrderRepository;
        $this->outlayRepositoryInterface = $outlayRepositoryInterface;
        $this->deptRepositoryInterface = $deptRepositoryInterface;
        $this->customsStatementRepositoryInterface = $customsStatementRepositoryInterface;
        $this->pr = $pr;
    }


    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function order()
    {

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

        if (request('source_id')){
            if (request('needConditionReport') != null && request('needConditionReport') == 'need') {
                $where = [
                    [
                        'min_qty', '>', 0
                    ],
                    [
                        'stock', '<', DB::raw('min_qty')
                    ],
                    [
                        'source_id', request('source_id')
                    ],
                    [
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'
                    ]
                ];
            }
            elseif (request('needConditionReport') != null && request('needConditionReport') == 'stock'){
                $where = [];
                $where[]= [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
                $where[]= ['source_id', '=', request('source_id')];
            }
            else {
                ////test
                $where = [
                    [
                        'min_qty', '>', 0
                    ],
                    [
                        'is_retired', 0
                    ],
                    [
                        'source_id', request('source_id')
                    ],
                    [
                        'stock', '<', DB::raw('min_qty')
                    ],
                    [
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'
                    ]
                ];
            }
        }
        else{
            if (request('needConditionReport') != null && request('needConditionReport') == 'need') {
                $where = [
                    [
                        'min_qty', '>', 0
                    ],
                    [
                        'stock', '<', DB::raw('min_qty')
                    ],
                    [
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'
                    ]
                ];
            }
            elseif (request('needConditionReport') != null && request('needConditionReport') == 'stock'){
                $where = [];
                $where[]= [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
            }
            else {
                ////test
                $where = [
                    [
                        'min_qty', '>', 0
                    ],
                    [
                        'is_retired', 0
                    ],
                    [
                        'stock', '<', DB::raw('min_qty')
                    ],
                    [
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'
                    ]
                ];
            }
>>>>>>> 2957ccbc2e14e2b28a9112b6c63765416a3bf6c1
        }


        $data = $this->productRepositoryInterface->get($where)->sortBy('date');
        return NeedStocksReportResource::collection($data);
    }



    private function buildConditions()
    {
        try {
            $needCondition = request('needConditionReport');

            if ($needCondition === 'need') {
                return [
                    ['min_qty', '>', 0],
                    ['stock', '<', DB::raw('min_qty')],
                    [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false']
                ];
            } elseif ($needCondition === 'stock') {
                return [
                    [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false']
                ];
            } else {
                return [
                    ['min_qty', '>', 0],
                    ['is_retired', 0],
                    ['stock', '<', DB::raw('min_qty')],
                    [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false']
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Condition build failed: ' . $e->getMessage());
            return [];
        }
    }

    public function exportImagesZip()
    {
        try {
            $conditions = $this->buildConditions();
            $chunkSize = 100;
            $totalProducts = Product::where($conditions)->count();
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
        $website = config('app.url');

        $products = Product::with(['source', 'media'])
            ->where($manifest['conditions'])
            ->offset($offset)
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
            'Order_Quantity',
            'Purchases_Quantity',
            'PriceAll',
            'Real_Price_All',
            'Source_Sku',
            'Link',
            'source'
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
                $stock,
                $price,
                $realPrice,
                $product->min_qty,
                $product->order_qty,
                $product->order_qty,
                $priceAll,
                $realPriceAll,
                $product->source_sku,
                "{$website}/product/{$product->sku}",
                $product->source ? $product->source->name : ''
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
}
