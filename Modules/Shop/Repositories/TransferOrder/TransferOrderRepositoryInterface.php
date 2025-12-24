<?php

namespace Modules\Shop\Repositories\TransferOrder;

use App\Repositories\Base\BaseRepository;

interface TransferOrderRepositoryInterface extends BaseRepository
{
    public function createOrder($data);
    public function updateOrder($id, $data);
    public function completeOrder($id);
    public function cancelOrder($id);
    public function getStatistics($userId = null, $startDate = null, $endDate = null);
}
