<?php

namespace App\Repositories\Base;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface BaseRepository
{

    /**
     * @param $id
     * @param array $with
     * @return Model
     */
    public function findOrFail($id, $with = []);

    /**
     * @param array $where
     * @param array $with
     * @return mixed
     */
    public function firstOrFail($where=[] , $with = []);

    /**
     * @param $data
     * @return Model
     */
    public function create($data);

    /**
     * @param $id
     * @param $data
     * @return Model
     */
    public function update($id, $data);

    /**
     * @param $id
     * @return boolean
     * @throws Exception
     */
    public function delete($id);

    /**
     * @param string $name
     * @param string $key
     * @return Collection
     */
    public function pluck($name = 'name', $key = 'id', $wheres = []);

    /**
     * @param array $wheres
     * @return mixed
     */
    public function get($wheres = [], $with = []);

    /**
     * @param int $count
     * @param array $wheres
     * @param array $with
     * @param array $withCount
     * @return mixed
     */
    public function paginate($count = 20, $wheres = [], $with = [], $withCount = []);

    /**
     * @param string $function
     * @return array|mixed
     */
    public function cached($function = 'All');

    /**
     * @param array $searchColumns
     * @param array $with
     * @param array $whereHas
     * @return mixed
     */
    public function datatable($searchColumns = [], $with = [], $whereHas = []);

    public function model();

}
