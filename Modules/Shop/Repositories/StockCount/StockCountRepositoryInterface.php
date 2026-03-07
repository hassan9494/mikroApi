<?php

namespace Modules\Shop\Repositories\StockCount;

use App\Repositories\Base\BaseRepository;

interface StockCountRepositoryInterface extends BaseRepository
{
    public function createCount($data);
    public function updateCount($id, $data);
    public function addProducts($countId, $products);
    public function updateProduct($countId, $productId, $data);
    public function removeProduct($countId, $productId);
    public function submitCount($id);
    public function approveCount($id, $approvedBy, $notes = null);
    public function rejectCount($id, $rejectedBy, $reason = null);
    public function getUserCounts($userId, $filters = []);
    public function getAllCounts($filters = []);
    public function getCountHistory($countId);
    public function changeStatus($id, $status, $changedBy, $reason = null);
}
