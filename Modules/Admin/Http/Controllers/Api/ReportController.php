<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Http\Resources\CustomsStatementResource;
use Modules\Admin\Http\Resources\DeptResource;
use Modules\Admin\Http\Resources\NeedStocksReportResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Resources\OutlayResource;
use Modules\Admin\Http\Resources\ProductSalesReportResource;
use Modules\Admin\Http\Resources\ProductStocksReportResource;
use Modules\Common\Repositories\CustomsStatement\CustomsStatementRepositoryInterface;
use Modules\Common\Repositories\Dept\DeptRepositoryInterface;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepository;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

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
    private ProductRepository $pr;

    /**
     * ReportController constructor.
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param OrderRepositoryInterface $orderRepository
     * @param OutlayRepositoryInterface $outlayRepositoryInterface
     * @param DeptRepositoryInterface $deptRepositoryInterface
     * @param CustomsStatementRepositoryInterface $customsStatementRepositoryInterface
     */
    public function __construct(
        ProductRepositoryInterface $productRepositoryInterface,
        OrderRepositoryInterface $orderRepository,
        OutlayRepositoryInterface $outlayRepositoryInterface,
        DeptRepositoryInterface $deptRepositoryInterface,
        CustomsStatementRepositoryInterface $customsStatementRepositoryInterface,
        ProductRepository $pr
    )
    {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->orderRepository = $orderRepository;
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
//        dd('kdjkdj');
        $where = [
            [
                'taxed_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'taxed_at', '<=', request('to', now()->endOfMonth())
            ],
            [
                'options->taxed', true
            ]
        ];
        $orWhere = [
            [
                'updated_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'updated_at', '<=', request('to', now()->endOfMonth())
            ],
            [
                'options->taxed', true
            ]
        ];
        if ($exempt = request('exempt')) {
            $where[] = ['options->tax_exempt', $exempt];
            $orWhere[] = ['options->tax_exempt', $exempt];
        }
        if ($zero = request('zero')) {
            $where[] = ['options->tax_exempt', true];
            $where[] = ['options->tax_zero', $zero];
            $orWhere[] = ['options->tax_exempt', true];
            $orWhere[] = ['options->tax_zero', $zero];
        }
        if ($status = request('status')) {
            $where[] = ['status', $status];
            $orWhere[] = ['status', $status];
        }
//        dd($orWhere);
        $data = $this->orderRepository->get($where, ['products'],$orWhere)->sortBy('tax_number');
        return OrderResource::collection($data);
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

        $where = [
            [
                'taxed_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'taxed_at', '<=', request('to', now()->endOfMonth())
            ]
        ];
        $orWhere = [
            [
                'updated_at', '>=', request('from', now()->startOfMonth())
            ],
            [
                'updated_at', '<=', request('to', now()->endOfMonth())
            ]
        ];
        if ($dept = request('dept')) {
            $where[] = ['options->dept', $dept];
            $orWhere[] = ['options->dept', $dept];
        } else {
            $where[] = ['options->dept', true];
            $orWhere[] = ['options->dept', true];
        }
        if ($status = request('status')) {
            $where[] = ['status', $status];
            $orWhere[] = ['status', $status];
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
        return Datatable::make($this->productRepositoryInterface->model())
            ->search(['id', 'name->en', 'sku'])
            ->resource(ProductSalesReportResource::class)
            ->json();
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function productStock(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        if (request('needConditionReport') != null && request('needConditionReport') == 'need') {
            $where = [
                [
                    'min_qty', '>', 0
                ],
                [
                    'stock', '<', DB::raw('min_qty')
                ]
            ];
        } else {
            $where = [];
        }

        $data = $this->productRepositoryInterface->get($where)->sortBy('date');
        return NeedStocksReportResource::collection($data);
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
            ->search(['id', 'name', 'sku', 'stock', 'min_qty'])
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
        $whereHas['completedOrders'] = function ($q) use ($from, $to) {
            if ($from) $q->whereDate('inspection_date', '>=', $from);
            if ($to) $q->whereDate('inspection_date', '<=', $to);
        };
        $data = $this->productRepositoryInterface->model();
        return Datatable::make($data)
            ->whereHas($whereHas)
            ->search(['id', 'name', 'sku', 'stock', 'min_qty'])
            ->resource(ProductSalesReportResource::class)
            ->json();
    }
}
