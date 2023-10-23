<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\NeedStocksReportResource;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Resources\OutlayResource;
use Modules\Admin\Http\Resources\ProductSalesReportResource;
use Modules\Admin\Http\Resources\ProductStocksReportResource;
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
    private ProductRepositoryInterface $ProductRepositoryInterface;
    /**
     * @var OutlayRepositoryInterface
     */
    private OutlayRepositoryInterface $outlayRepositoryInterface;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    private ProductRepository $pr;

    /**
     * ReportController constructor.
     * @param ProductRepositoryInterface $ProductRepositoryInterface
     * @param OrderRepositoryInterface $orderRepository
     * @param OutlayRepositoryInterface $outlayRepositoryInterface
     */
    public function __construct(
        ProductRepositoryInterface $ProductRepositoryInterface,
        OrderRepositoryInterface $orderRepository,
        OutlayRepositoryInterface $outlayRepositoryInterface,
        ProductRepository $pr
    )
    {
        $this->ProductRepositoryInterface = $ProductRepositoryInterface;
        $this->orderRepository = $orderRepository;
        $this->outlayRepositoryInterface = $outlayRepositoryInterface;
        $this->pr=$pr;
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
                'taxed_at', '<=', request('to', now()->endOfMonth())
            ],
            [
                'options->taxed', true
            ]
        ];
        if ($exempt = request('exempt')) {
            $where[] = ['options->tax_exempt', $exempt];
        }
        if ($status = request('status')) {
            $where[] = ['status', $status];
        }
        $data = $this->orderRepository->get($where, ['products'])->sortBy('tax_number');
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
     * @return JsonResponse
     */
    public function productSales(): JsonResponse
    {
        return Datatable::make($this->ProductRepositoryInterface->model())
            ->search(['id', 'name->en', 'sku'])
            ->resource(ProductSalesReportResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function productStock(): JsonResponse
    {
        return Datatable::make($this->ProductRepositoryInterface->model())
            ->search(['id', 'name', 'sku'])
            ->resource(ProductStocksReportResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function productNeed(): JsonResponse
    {
        $where = [
            [
                'min_qty', '>',0
            ],
            [
                'stock', '<', "min_qty"
            ]
        ];
        return Datatable::make($this->ProductRepositoryInterface->model())
            ->search(['id', 'name', 'sku','stock','min_qty'])
            ->resource(NeedStocksReportResource::class)
            ->json();
    }
}
