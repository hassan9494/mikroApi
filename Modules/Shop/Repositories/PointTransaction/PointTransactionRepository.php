<?php

namespace Modules\Shop\Repositories\PointTransaction;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\PointTransaction;

/**
 * Class PointTransactionRepository
 * @package Modules\Shop\Repositories\PointTransaction
 */
class PointTransactionRepository extends EloquentRepository implements PointTransactionRepositoryInterface
{
    /**
     * @var PointTransaction
     */
    protected $model;

    /**
     * PointTransactionRepository constructor.
     * @param PointTransaction $model
     */
    public function __construct(PointTransaction $model)
    {
        parent::__construct($model);
    }

    /**
     * Get transactions for a specific user
     *
     * @param int $userId
     * @param int $limit
     * @return mixed
     */
    public function getByUserId(int $userId, int $limit = 20)
    {
        return $this->model
            ->forUser($userId)
            ->with('order:id,status,total')
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get transactions by type
     *
     * @param string $type
     * @param int $limit
     * @return mixed
     */
    public function getByType(string $type, int $limit = 20)
    {
        return $this->model
            ->ofType($type)
            ->with(['user:id,name,email', 'order:id,status,total'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get transactions for an order
     *
     * @param int $orderId
     * @return mixed
     */
    public function getByOrderId(int $orderId)
    {
        return $this->model
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
