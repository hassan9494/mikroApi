<?php

namespace App\Repositories\User;
use App\Repositories\Base\BaseRepository;


/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface UserRepositoryInterface extends BaseRepository
{

    /**
     * @param $email
     * @param array $with
     * @return mixed
     */
    public function findOrFailByEmail($email, $with = []);

    /**
     * @param $email
     * @param array $with
     * @return mixed
     */
    public function firstByEmail($email, $with = []);

    /**
     * @param $q
     * @param int $limit
     * @return mixed
     */
    public function autocomplete($q, $limit = 20);

    /**
     * @param $q
     * @param int $limit
     * @return mixed
     */
    public function autocompletecashier($q, $limit = 20);

}
