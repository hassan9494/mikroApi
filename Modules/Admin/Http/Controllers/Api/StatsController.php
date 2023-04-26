<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\OrderResource;
use Modules\Shop\Repositories\Order\OrderRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;

class StatsController extends Controller
{

    use ApiResponser;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $repository;

    /**
     * OrderController constructor.
     * @param OrderRepositoryInterface $repository
     */
    public function __construct(OrderRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function sales()
    {
        $data = $this->repository->sales();
        return $this->success($data);
    }

}
