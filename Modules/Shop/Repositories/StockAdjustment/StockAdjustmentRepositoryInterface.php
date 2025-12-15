<?php

namespace Modules\Shop\Repositories\StockAdjustment;

use App\Repositories\Base\BaseRepository;

interface StockAdjustmentRepositoryInterface extends BaseRepository
{
    public function createRequest($data);
    public function approveRequest($id, $approvedBy, $notes = null);
    public function rejectRequest($id, $rejectedBy, $reason);
    public function getPendingRequests($filters = []);
    public function getAllRequests($filters = []); // ADD THIS
    public function getUserRequests($userId, $filters = []);
    public function getByProduct($productId, $filters = []);
    public function applyStockAdjustment($adjustment);
    public function getStatistics($userId = null, $startDate = null, $endDate = null);
}
