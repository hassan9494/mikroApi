<?php

namespace Modules\Shop\Repositories\Order;

use App\Models\User;
use App\Repositories\Base\BaseRepository;
use Modules\Shop\Entities\Address;
use Modules\Shop\Entities\Order;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface OrderRepositoryInterface extends BaseRepository
{

    /**
     * @param array $data
     * @param bool $checkStock
     * @return Order
     */
    public function make(array $data): Order;

    /**
     * @param array $data
     * @param Address $address
     * @return Order
     */
    public function makeByUser(array $data, Address $address,User $user): Order;

    public function makeByEmployee(array $data, User $employee): Order;

    /**
     * @param array $data
     * @return Order
     */
    public function makeByGuest(array $data): Order;

    /**
     * @param $userId
     * @param array $with
     * @return mixed
     */
    public function user($userId, $with = []);

    /**
     * @param $id
     * @param $status
     * @return mixed
     */
    public function status($id, $status);

    /**
     * @param $id
     * @param $data
     * @param bool $checkStock
     * @return mixed
     */
    public function saveOrder($id, $data, bool $checkStock = true);

    /**
     * @param $q
     * @param int $limit
     * @return mixed
     */
    public function autocomplete($q, $limit = 20);

    /**
     * @return mixed
     */
    public function sales();

    public function get($wheres = [], $with = [],$orWhere = []);



}
