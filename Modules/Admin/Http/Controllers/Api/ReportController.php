<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Admin\Http\Resources\ProductSalesReportResource;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class ReportController extends Controller
{

    use ApiResponser;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $ProductRepositoryInterface;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * ReportController constructor.
     * @param ProductRepositoryInterface $ProductRepositoryInterface
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ProductRepositoryInterface $ProductRepositoryInterface,
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->ProductRepositoryInterface = $ProductRepositoryInterface;
        $this->orderRepository = $orderRepository;
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
     * @return JsonResponse
     */
    public function productSales(): JsonResponse
    {
        return Datatable::make($this->ProductRepositoryInterface->model())
            ->search(['id', 'name->en', 'sku'])
            ->resource(ProductSalesReportResource::class)
            ->json();
    }
}
