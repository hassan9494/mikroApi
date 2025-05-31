<?php

namespace Modules\Shop\Repositories\ReturnOrder;

use App\Repositories\Base\BaseRepository;
use Modules\Shop\Entities\ReturnOrder;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface ReturnOrderRepositoryInterface extends BaseRepository
{

    /**
     * @param array $data
     * @param bool $checkStock
     * @return ReturnOrder
     */
    public function make(array $data): ReturnOrder;

    /**
     * @param $id
     * @param $status
     * @return mixed
     */
    public function status($id, $status,$products);

    /**
     * @param $id
     * @param $data
     * @param bool $checkStock
     * @return mixed
     */
    public function saveOrder($id, $data, bool $checkStock = true);

    /**
     * @return mixed
     */
    public function sales();

    public function get($wheres = [], $with = [],$orWhere = []);



}
