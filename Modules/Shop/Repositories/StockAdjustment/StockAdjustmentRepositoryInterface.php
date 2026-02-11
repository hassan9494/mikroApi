<?php

namespace Modules\Shop\Repositories\StockAdjustment;

use App\Repositories\Base\BaseRepository;

interface StockAdjustmentRepositoryInterface extends BaseRepository
{
    public function createRequest($data);
    public function approveRequest($id, $approvedBy, $notes = null);
    public function rejectRequest($id, $rejectedBy, $reason);
    public function updateRequest($id, $data); // NEW
    public function changeStatus($id, $status, $changedBy, $reason = null); // NEW
    public function getForEditing($id); // NEW
    public function getPendingRequests($filters = []);
    public function getAllRequests($filters = []);
    public function getUserRequests($userId, $filters = []);
    public function getByProduct($productId, $filters = []);
    public function applyStockAdjustment($adjustment);
    public function getStatistics($userId = null, $startDate = null, $endDate = null);
}
