<?php

namespace Modules\Shop\Repositories\PointTransaction;

use App\Repositories\Base\BaseRepository;

/**
 * Interface PointTransactionRepositoryInterface
 * @package Modules\Shop\Repositories\PointTransaction
 */
interface PointTransactionRepositoryInterface extends BaseRepository
{
    /**
     * Get transactions for a specific user
     *
     * @param int $userId
     * @param int $limit
     * @return mixed
     */
    public function getByUserId(int $userId, int $limit = 20);

    /**
     * Get transactions by type
     *
     * @param string $type
     * @param int $limit
     * @return mixed
     */
    public function getByType(string $type, int $limit = 20);

    /**
     * Get transactions for an order
     *
     * @param int $orderId
     * @return mixed
     */
    public function getByOrderId(int $orderId);
}
