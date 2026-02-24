<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Http\Resources\AllProductSalesReportResource;
use Modules\Admin\Http\Resources\CustomsStatementResource;
use Modules\Admin\Http\Resources\DeptResource;
use Modules\Admin\Http\Resources\NeedStocksReportResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Resources\OutlayResource;
use Modules\Admin\Http\Resources\ProductOrderExportResource;
use Modules\Admin\Http\Resources\ProductSalesReportResource;
use Modules\Admin\Http\Resources\ProductStocksReportResource;
use Modules\Admin\Http\Resources\ReturnOrderResource;
use Modules\Admin\Http\Resources\TransactionResource;
use Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface;
use Modules\Common\Repositories\Dept\DeptRepositoryInterface;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ReturnOrder;
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
        $taxType = request('taxType');
        switch ($taxType){
            case 'exempt':
                $where[] = ['options->tax_exempt', true];
                $where[] = ['options->tax_zero', false];
                break;
            case 'zero_rate' :
                $where[] = ['options->tax_exempt', true];
                $where[] = ['options->tax_zero', true];
                break;
            case 'taxable' :
                $where[] = ['options->tax_exempt', false];
                $where[] = ['options->tax_zero', false];
                break;
            default:
                break;
        }
        $data = $this->orderRepository->get($where, ['products'])->sortBy('tax_number');
        return OrderResource::collection($data);
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function return_order()
    {
        $query = ReturnOrder::query();
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
        if (request('from')){
            $query->where('created_at','>=',request('from', now()->startOfMonth()));
        }
        if (request('newTo')){
            $query->where('created_at','<=',request('newTo', now()->endOfMonth()));
        }
        $status = request('status');
        $taxType = request('taxType');
        if ($status){
            if ($status == 0 || $status == 1 || $status == "0" || $status == "1" ) {
                $query->where('is_migrated',$status);
                $where[] = ['is_migrated', $status];
            }
        }
        $whereHas = [];
        if ($taxType){
            switch ($taxType){
                case  'taxable':
                    $query->whereHas('order',function ($q) use ($taxType) {
                        $q->where('options->taxed',true)
                            ->where('options->tax_exempt',false)
                            ->where('options->tax_zero',false);
                    });
                    break;
                case  'exempt':
                    $query->whereHas('order',function ($q) use ($taxType) {
                        $q->where('options->taxed',true)
                            ->where('options->tax_exempt',true)
                            ->where('options->tax_zero',false);
                    });
                    break;
                case  'zero_rate':
                    $query->whereHas('order',function ($q) use ($taxType) {
                        $q->where('options->taxed',true)
                            ->where('options->tax_exempt',true)
                            ->where('options->tax_zero',true);
                    });
                    break;
                default:
                    break;
            }
        }

        $data = $query->where('status','COMPLETED')->get();
//        $data = $this->returnOrderRepository->get($where, ['products','order'])->sortBy('id');

        return ReturnOrderResource::collection($data);
    }


    public function product()
    {
        ini_set('memory_limit', '1024M');
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


    public function products_sales_order_old()
    {
        $from = request('from', now()->startOfMonth());
        $to = request('to', now()->endOfMonth());
        $productId = request('id');

        if (!$productId) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        // Get the product
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Get all KIT products that contain this product
        $kitProductIds = DB::table('product_kit')
            ->where('product_id', $productId)
            ->pluck('kit_id')
            ->toArray();

        // Combine target product ID with kit product IDs
        $allProductIds = array_merge([$productId], $kitProductIds);

        // Build query
        $orders = Order::query()
            ->whereIn('status', ['COMPLETED','PROCESSING'])
            ->whereDate('taxed_at', '>=', $from)
            ->whereDate('taxed_at', '<=', $to)
            ->where(function($query) use ($allProductIds) {
                $query->whereHas('products', function($q) use ($allProductIds) {
                    $q->whereIn('products.id', $allProductIds);
                });
            })
            ->with(['products' => function($query) use ($allProductIds) {
                $query->whereIn('products.id', $allProductIds)
                    ->withPivot('quantity', 'price', 'real_price', 'discount', 'product_name');
            }])
            ->orderBy('taxed_at', 'desc')
            ->get();

        return ProductOrderExportResource::collection($orders, $productId, $kitProductIds);
    }


    public function products_sales_order()
    {
        $from = request('from', now()->startOfMonth());
        $to = request('to', now()->endOfMonth());
        $productId = request('id');

        if (!$productId) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        // Get kit product IDs
        $kitProductIds = DB::table('product_kit')
            ->where('product_id', $productId)
            ->pluck('kit_id')
            ->toArray();

        $allProductIds = array_merge([$productId], $kitProductIds);

        // Get orders
        $orders = Order::whereIn('status', ['COMPLETED','PROCESSING'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereHas('products', function($q) use ($allProductIds) {
                $q->whereIn('products.id', $allProductIds);
            })
            ->with(['products' => function($query) use ($allProductIds) {
                $query->whereIn('products.id', $allProductIds)
                    ->withPivot('quantity');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get kit quantities from product_kit table
        $kitQuantities = DB::table('product_kit')
            ->where('product_id', $productId)
            ->get()
            ->keyBy('kit_id');

        // Process each order
        $formattedOrders = $orders->map(function($order) use ($productId, $kitQuantities) {
            $directQuantity = 0;
            $kitQuantity = 0;

            foreach ($order->products as $product) {
                if ($product->id == $productId) {
                    $directQuantity = $product->pivot->quantity;
                } else {
                    // Check if this product is a kit containing our target product
                    if ($kitQuantities->has($product->id)) {
                        $kitInfo = $kitQuantities->get($product->id);
                        $kitQuantity += ($product->pivot->quantity * $kitInfo->quantity);
                    }
                }
            }

            // Parse customer data
            $customer = $order->customer;
            if (is_string($customer)) {
                $customer = json_decode($customer, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $customer = ['name' => '', 'phone' => ''];
                }
            }

            return [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'completed_at' => $order->completed_at,
                'taxed_at' => $order->taxed_at,
                'customer' => $customer,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'tax_number' => $order->tax_number,
                'direct_quantity' => $directQuantity,
                'kit_quantity' => $kitQuantity,
                'total_product_quantity' => $directQuantity + $kitQuantity,
                'is_kit_sale' => $kitQuantity > 0,
                'identity_number_type' => $order->identity_number_type,
                'customer_identity_number' => $order->customer_identity_number,
            ];
        });

        return response()->json([
            'data' => $formattedOrders
        ]);
    }


    public function all_products_sales_order()
    {
        $from = request('from', now()->startOfMonth());
        $to = request('to', now()->endOfMonth());
        $productId = request('id');

        if (!$productId) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }

        // Get kit product IDs
        $kitProductIds = DB::table('product_kit')
            ->where('product_id', $productId)
            ->pluck('kit_id')
            ->toArray();

        $allProductIds = array_merge([$productId], $kitProductIds);

        // Get orders
        $orders = Order::whereIn('status', ['COMPLETED','PROCESSING','CANCELED','PENDING'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereHas('products', function($q) use ($allProductIds) {
                $q->whereIn('products.id', $allProductIds);
            })
            ->with(['products' => function($query) use ($allProductIds) {
                $query->whereIn('products.id', $allProductIds)
                    ->withPivot('quantity');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get kit quantities from product_kit table
        $kitQuantities = DB::table('product_kit')
            ->where('product_id', $productId)
            ->get()
            ->keyBy('kit_id');

        // Process each order
        $formattedOrders = $orders->map(function($order) use ($productId, $kitQuantities) {
            $directQuantity = 0;
            $kitQuantity = 0;

            foreach ($order->products as $product) {
                if ($product->id == $productId) {
                    $directQuantity = $product->pivot->quantity;
                } else {
                    // Check if this product is a kit containing our target product
                    if ($kitQuantities->has($product->id)) {
                        $kitInfo = $kitQuantities->get($product->id);
                        $kitQuantity += ($product->pivot->quantity * $kitInfo->quantity);
                    }
                }
            }

            // Parse customer data
            $customer = $order->customer;
            if (is_string($customer)) {
                $customer = json_decode($customer, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $customer = ['name' => '', 'phone' => ''];
                }
            }

            return [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'completed_at' => $order->completed_at,
                'taxed_at' => $order->taxed_at,
                'customer' => $customer,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'tax_number' => $order->tax_number,
                'direct_quantity' => $directQuantity,
                'kit_quantity' => $kitQuantity,
                'total_product_quantity' => $directQuantity + $kitQuantity,
                'is_kit_sale' => $kitQuantity > 0,
                'identity_number_type' => $order->identity_number_type,
                'customer_identity_number' => $order->customer_identity_number,
            ];
        });

        return response()->json([
            'data' => $formattedOrders
        ]);
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
        ini_set('memory_limit', '1024M');
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
        $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit')) = 'false'")
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
            ->with(['kit', 'inKits']) // Eager load both relationships
            ->search('id', 'name', 'sku', 'stock', 'min_qty')
            ->resource(ProductSalesReportResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function allProductSale(): JsonResponse
    {
        $from = request('from');
        $to = request('to');

        $whereHas['orders'] = function ($q) use ($from, $to) {
            if ($from) $q->whereDate('updated_date', '>=', $from);
            if ($to) $q->whereDate('updated_date', '<=', $to);
        };

        $data = $this->productRepositoryInterface->model();

        return Datatable::make($data)
            ->whereHas($whereHas)
            ->with(['kit', 'inKits']) // Eager load both relationships
            ->search('id', 'name', 'sku', 'stock', 'min_qty')
            ->resource(AllProductSalesReportResource::class)
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

    /**
     * Get stock movement for a specific product
     */
    public function productStockMovement(Request $request)
    {
        try {
            $productId = $request->get('product_id');
            $from = $request->get('from');
            $to = $request->get('to');
            $filterType = $request->get('filter_type', 'all');

            if (!$productId) {
                return response()->json([
                    'message' => 'Product ID is required'
                ], 400);
            }

            // Get product details
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found'
                ], 404);
            }

            // Initialize queries array
            $queries = [];
            $dateExpr = DB::raw("COALESCE(o.taxed_at, o.completed_at, o.created_at)");

            // 1. SALES QUERY - All columns must be present
            if (in_array($filterType, ['all', 'sale'])) {
                $salesQuery = DB::table('orders as o')
                    ->join('order_products as op', 'o.id', '=', 'op.order_id')
                    ->where('op.product_id', $productId)
                    ->whereIn('o.status', ['COMPLETED', 'PROCESSING'])
                    ->select(
                        DB::raw("'sale' as type"),
                        'o.id as reference_id',
                        DB::raw('o.id as reference_number'),
                        DB::raw("DATE_FORMAT({$dateExpr}, '%Y-%m-%dT%TZ') as date"),
                        DB::raw('0 as increase_quantity'),
                        DB::raw('0 as decrease_quantity'),
                        DB::raw('0 as purchases_quantity'),
                        'op.quantity as sales_quantity',
                        'op.price as sale_price', // Sale price per unit
                        'op.discount as discount', // Discount amount
                        DB::raw('NULL as base_purchases_price'),
                        DB::raw('NULL as exchange_factor'),
                        DB::raw('NULL as distributer_price'),
                        DB::raw('NULL as normal'),
                        DB::raw('NULL as purchases_price'), // Real price for purchases
                        DB::raw('(op.quantity * op.price) - op.discount as total_amount'),
                        DB::raw("CONCAT('Order #', o.id, ' (', o.status, ')') as description"),
                        DB::raw("'sale' as source_type"),
                        'o.status as order_status'
                    );

                // Apply date filter
                if ($from && $to) {
                    $salesQuery->whereBetween('o.taxed_at', [$from, $to]);
                } elseif ($from) {
                    $salesQuery->where('o.taxed_at', '>=', $from);
                } elseif ($to) {
                    $salesQuery->where('o.taxed_at', '<=', $to);
                }

                $queries[] = $salesQuery;
            }

            // 2. PURCHASES QUERY - Must have same columns as sales query
            if (in_array($filterType, ['all', 'purchase'])) {
                $purchasesQuery = DB::table('invoices as i')
                    ->join('invoice_products as ip', 'i.id', '=', 'ip.invoice_id')
                    ->where('ip.product_id', $productId)
                    ->where('i.status', 'COMPLETED')
                    ->select(
                        DB::raw("'purchase' as type"),
                        'i.id as reference_id',
                        'i.number as reference_number',
                        'i.date as date',
                        DB::raw('0 as increase_quantity'),
                        DB::raw('0 as decrease_quantity'),
                        'ip.quantity as purchases_quantity',
                        DB::raw('0 as sales_quantity'),
                        DB::raw('NULL as sale_price'), // Not applicable for purchases
                        DB::raw('NULL as discount'), // Not applicable for purchases
                        'ip.base_purchases_price', // Purchases price (base price)
                        'ip.exchange_factor',
                        'ip.distributer_price',
                        'ip.normal',
                        'ip.purchases_price as purchases_price', // Real price
                        DB::raw('ip.quantity * ip.purchases_price as total_amount'),
                        DB::raw("CONCAT('Invoice #', i.number) as description"),
                        DB::raw("'purchase' as source_type"),
                        DB::raw('NULL as order_status')
                    );

                // Apply date filter
                if ($from && $to) {
                    $purchasesQuery->whereBetween('i.date', [$from, $to]);
                } elseif ($from) {
                    $purchasesQuery->where('i.date', '>=', $from);
                } elseif ($to) {
                    $purchasesQuery->where('i.date', '<=', $to);
                }

                $queries[] = $purchasesQuery;
            }

            // 3. ADJUSTMENTS QUERY - Must have same columns as other queries
            if (in_array($filterType, ['all', 'adjustment', 'adjustment-inc', 'adjustment-dec'])) {
                $adjustmentsQuery = DB::table('stock_adjustments as sa')
                    ->where('sa.product_id', $productId)
                    ->whereIn('sa.status', ['approved']);

                // Apply adjustment type filter
                if ($filterType === 'adjustment-inc') {
                    $adjustmentsQuery->where('sa.adjustment_type', 'increase');
                } elseif ($filterType === 'adjustment-dec') {
                    $adjustmentsQuery->where('sa.adjustment_type', 'decrease');
                }

                $adjustmentsQuery->select(
                    DB::raw("'adjustment' as type"),
                    'sa.id as reference_id',
                    DB::raw('sa.id as reference_number'),
                    'sa.created_at as date',
                    DB::raw("CASE WHEN sa.adjustment_type = 'increase' THEN sa.quantity ELSE 0 END as increase_quantity"),
                    DB::raw("CASE WHEN sa.adjustment_type = 'decrease' THEN sa.quantity ELSE 0 END as decrease_quantity"),
                    DB::raw('0 as purchases_quantity'),
                    DB::raw('0 as sales_quantity'),
                    DB::raw('NULL as sale_price'),
                    DB::raw('NULL as discount'),
                    DB::raw('NULL as base_purchases_price'),
                    DB::raw('NULL as exchange_factor'),
                    DB::raw('NULL as distributer_price'),
                    DB::raw('NULL as normal'),
                    DB::raw('NULL as purchases_price'),
                    DB::raw('NULL as total_amount'),
                    DB::raw("CONCAT('Adjustment #', sa.id, ' - ', sa.adjustment_type, ' (', COALESCE(sa.reason, ''), ')') as description"),
                    DB::raw("'adjustment' as source_type"),
                    DB::raw('NULL as order_status')
                );

                // Apply date filter
                if ($from && $to) {
                    $adjustmentsQuery->whereBetween('sa.created_at', [$from, $to]);
                } elseif ($from) {
                    $adjustmentsQuery->where('sa.created_at', '>=', $from);
                } elseif ($to) {
                    $adjustmentsQuery->where('sa.created_at', '<=', $to);
                }

                $queries[] = $adjustmentsQuery;
            }

// 4. RETURNS QUERY
            if (in_array($filterType, ['all', 'return'])) {
                $returnsQuery = DB::table('return_orders as ro')
                    ->join('return_order_products as rop', 'ro.id', '=', 'rop.return_order_id')
                    ->where('rop.product_id', $productId)
                    ->where('ro.status', 'COMPLETED')
                    ->select(
                        DB::raw("'return' as type"),
                        'ro.id as reference_id',
                        'ro.number as reference_number',
                        DB::raw("DATE_FORMAT(ro.created_at, '%Y-%m-%dT%TZ') as date"),
                        'rop.returned_quantity as increase_quantity',
                        DB::raw('0 as decrease_quantity'),
                        DB::raw('0 as purchases_quantity'),
                        DB::raw('0 as sales_quantity'),
                        'rop.price as sale_price',
                        'rop.discount as discount',
                        DB::raw('NULL as base_purchases_price'),
                        DB::raw('NULL as exchange_factor'),
                        DB::raw('NULL as distributer_price'),
                        DB::raw('NULL as normal'),
                        DB::raw('NULL as purchases_price'),
                        DB::raw('(rop.returned_quantity * rop.price) - rop.discount as total_amount'), // <-- added
                        DB::raw("CONCAT('Return Order #', ro.number) as description"),
                        DB::raw("'return' as source_type"),
                        DB::raw('NULL as order_status')
                    );

                // Apply date filters
                if ($from && $to) {
                    $returnsQuery->whereBetween('ro.created_at', [$from, $to]);
                } elseif ($from) {
                    $returnsQuery->where('ro.created_at', '>=', $from);
                } elseif ($to) {
                    $returnsQuery->where('ro.created_at', '<=', $to);
                }

                $queries[] = $returnsQuery;
            }

            // If no queries (shouldn't happen but just in case)
            if (empty($queries)) {
                return response()->json([
                    'data' => [
                        'items' => [],
                        'total' => 0,
                        'product' => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'image' => $product->getFirstMediaUrl('default'),
                            'stock' => $product->stock,
                            'stock_available' => $product->stock_available,
                            'store_available' => $product->store_available,
                            'min_qty' => $product->min_qty,
                            'price' => $product->price
                        ]
                    ]
                ]);
            }

            // Build union query
            $unionQuery = null;
            foreach ($queries as $index => $query) {
                if ($index === 0) {
                    $unionQuery = $query;
                } else {
                    $unionQuery = $unionQuery->unionAll($query);
                }
            }

            // Get total count
            $totalQuery = DB::query()->fromSub($unionQuery, 'combined');
            $total = $totalQuery->count();

            // Apply sorting and pagination
            $query = DB::query()->fromSub($unionQuery, 'combined')
                ->orderBy('date', 'desc');

            // Pagination
            $page = $request->get('page', 0);
            $limit = $request->get('limit', 20);
            $offset = $page * $limit;

            $items = $query->offset($offset)->limit($limit)->get();

            // Transform items to include proper links
            $items->transform(function ($item) {
                $frontUrl = config('app.front_url');

                // Set proper reference link based on source type
                if ($item->source_type === 'sale') {
                    $item->reference_link = $frontUrl . "/order/edit/{$item->reference_id}";
                    $item->reference_text = "Order #{$item->reference_id}";
                } elseif ($item->source_type === 'purchase') {
                    $item->reference_link = $frontUrl . "/invoice/edit/{$item->reference_id}";
                    $item->reference_text = "Invoice #{$item->reference_number}";
                } elseif ($item->source_type === 'adjustment') {
                    $item->reference_link = $frontUrl . "/stock-adjustment/edit/{$item->reference_id}";
                    $item->reference_text = "Adjustment #{$item->reference_number}";
                } elseif ($item->source_type === 'return') {   // <-- ADD THIS
                    $item->reference_link = $frontUrl . "/return-order/edit/{$item->reference_id}";
                    $item->reference_text = "Return Order #{$item->reference_number}";
                }


                return $item;
            });

            return response()->json([
                'data' => [
                    'items' => $items,
                    'total' => $total,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'image' => $product->getFirstMediaUrl('default'),
                        'stock' => $product->stock,
                        'stock_available' => $product->stock_available,
                        'store_available' => $product->store_available,
                        'min_qty' => $product->min_qty,
                        'price' => $product->price
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in productStockMovement: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Error fetching stock movement data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movement summary for ALL products
     */
    public function allProductsStockMovementSummary(Request $request)
    {
        try {
            $from = $request->get('from');
            $to = $request->get('to');
            $filterType = $request->get('filter_type', 'all');

            $productsQuery = Product::query()->with(['source']);

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $productsQuery->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('source_sku', 'like', "%{$search}%");
                });
            }

            $total = $productsQuery->count();
            $page = $request->get('page', 0);
            $limit = $request->get('limit', 20);
            $offset = $page * $limit;
            $products = $productsQuery->offset($offset)->limit($limit)->get();

            $items = [];
            foreach ($products as $product) {
                // Purchases
                $purchasesQuery = DB::table('invoice_products as ip')
                    ->join('invoices as i', 'ip.invoice_id', '=', 'i.id')
                    ->where('ip.product_id', $product->id)
                    ->where('i.status', 'COMPLETED');

                // Sales
                $salesQuery = DB::table('order_products as op')
                    ->join('orders as o', 'op.order_id', '=', 'o.id')
                    ->where('op.product_id', $product->id)
                    ->whereIn('o.status', ['COMPLETED', 'PROCESSING']);

                // Adjustments
                $adjustmentsQuery = DB::table('stock_adjustments as sa')
                    ->where('sa.product_id', $product->id)
                    ->whereIn('sa.status', ['approved']);

                // Returns (NEW)
                $returnsQuery = DB::table('return_orders as ro')
                    ->join('return_order_products as rop', 'ro.id', '=', 'rop.return_order_id')
                    ->where('rop.product_id', $product->id)
                    ->where('ro.status', 'COMPLETED');

                // Apply date filters if provided
                if ($from && $to) {
                    $purchasesQuery->whereBetween('i.date', [$from, $to]);
                    $salesQuery->whereBetween('o.taxed_at', [$from, $to]);
                    $adjustmentsQuery->whereBetween('sa.created_at', [$from, $to]);
                    $returnsQuery->whereBetween('ro.created_at', [$from, $to]);
                }

                // Apply filter type
                if ($filterType !== 'all') {
                    switch ($filterType) {
                        case 'purchase':
                            $salesQuery->whereRaw('1=0');
                            $adjustmentsQuery->whereRaw('1=0');
                            $returnsQuery->whereRaw('1=0');
                            break;
                        case 'sale':
                            $purchasesQuery->whereRaw('1=0');
                            $adjustmentsQuery->whereRaw('1=0');
                            $returnsQuery->whereRaw('1=0');
                            break;
                        case 'return':
                            $purchasesQuery->whereRaw('1=0');
                            $salesQuery->whereRaw('1=0');
                            $adjustmentsQuery->whereRaw('1=0');
                            break;
                        case 'adjustment':
                            $purchasesQuery->whereRaw('1=0');
                            $salesQuery->whereRaw('1=0');
                            $returnsQuery->whereRaw('1=0');
                            break;
                        case 'adjustment-inc':
                            $purchasesQuery->whereRaw('1=0');
                            $salesQuery->whereRaw('1=0');
                            $returnsQuery->whereRaw('1=0');
                            $adjustmentsQuery->where('adjustment_type', 'increase');
                            break;
                        case 'adjustment-dec':
                            $purchasesQuery->whereRaw('1=0');
                            $salesQuery->whereRaw('1=0');
                            $returnsQuery->whereRaw('1=0');
                            $adjustmentsQuery->where('adjustment_type', 'decrease');
                            break;
                    }
                }

                // Get totals
                $purchasesTotal = $purchasesQuery->sum('ip.quantity');
                $purchasesAmount = $purchasesQuery->sum(DB::raw('ip.quantity * ip.purchases_price'));

                $salesTotal = $salesQuery->sum('op.quantity');
                $salesAmount = $salesQuery->sum(DB::raw('(op.quantity * op.price) - op.discount'));
                $totalDiscount = $salesQuery->sum('op.discount');

                $increasesTotal = $adjustmentsQuery->clone()->where('adjustment_type', 'increase')->sum('quantity');
                $decreasesTotal = $adjustmentsQuery->clone()->where('adjustment_type', 'decrease')->sum('quantity');

                $returnsTotal = $returnsQuery->sum('rop.returned_quantity');
                $returnsAmount = $returnsQuery->sum(DB::raw('(rop.returned_quantity * rop.price) - rop.discount'));

                // Latest activity date
                $latestDates = [];
                $latestPurchase = $purchasesQuery->clone()->max('i.date');
                $latestSale = $salesQuery->clone()->max('o.taxed_at');
                $latestAdjustment = $adjustmentsQuery->clone()->max('created_at');
                $latestReturn = $returnsQuery->clone()->max('ro.created_at');
                if ($latestPurchase) $latestDates[] = $latestPurchase;
                if ($latestSale) $latestDates[] = $latestSale;
                if ($latestAdjustment) $latestDates[] = $latestAdjustment;
                if ($latestReturn) $latestDates[] = $latestReturn;
                $latestDate = !empty($latestDates) ? max($latestDates) : null;

                $netChange = $purchasesTotal + $increasesTotal + $returnsTotal - $salesTotal - $decreasesTotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_image' => $product->getFirstMediaUrl('default'),
                    'totalPurchases' => (int)$purchasesTotal,
                    'totalIncreases' => (int)$increasesTotal,
                    'totalReturns' => (int)$returnsTotal,
                    'totalSales' => (int)$salesTotal,
                    'totalDecreases' => (int)$decreasesTotal,
                    'totalPurchaseAmount' => (float)$purchasesAmount,
                    'totalSaleAmount' => (float)$salesAmount,
                    'totalDiscount' => (float)$totalDiscount,
                    'totalReturnAmount' => (float)$returnsAmount,
                    'netChange' => (int)$netChange,
                    'latest_date' => $latestDate,
                    'current_stock' => $product->stock,
                    'stock_available' => $product->stock_available,
                    'store_available' => $product->store_available,
                    'min_qty' => $product->min_qty,
                    'source_name' => $product->source ? $product->source->name : null,
                    'source_sku' => $product->source_sku,
                    'location' => $product->location,
                    'stock_location' => $product->stock_location,
                ];
            }

            return response()->json([
                'data' => [
                    'items' => $items,
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in allProductsStockMovementSummary: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching stock movement summary data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
