<?php

namespace Modules\Shop\Repositories\TaxExempt;

use App\Repositories\Base\BaseRepository;
use Illuminate\Support\Collection;
use Exception;
use Modules\Shop\Entities\TaxExempt;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface TaxExemptRepositoryInterface extends BaseRepository
{

    /**
     * @param $id
     * @param array $with
     * @return TaxExempt
     */
    public function findOrFail($id, $with = []);

    /**
     * @param $slug
     * @param array $with
     * @return mixed
     */
    public function findOrFailBySlug($slug, $with = []);

    /**
     * @param $data
     * @param $file
     * @return TaxExempt
     */
    public function create($data);

    /**
     * @param $id
     * @param $data
     * @param null $file
     * @return TaxExempt
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


//    /**
//     * @param $cateLimit
//     * @param $productLimit
//     * @param $wheres
//     * @return mixed
//     */
//    public function getWithProducts($cateLimit, $productLimit, $wheres = []);
//
//    /**
//     * @param $cateLimit
//     * @param $productLimit
//     * @return mixed
//     */
//    public function getBestSellersWithProducts($cateLimit, $productLimit);

    /**
     * @param array $searchColumns
     * @param array $with
     * @param array $whereHas
     * @return mixed
     */
    public function datatable($searchColumns = [], $with = [], $whereHas = []);

    public function model();

    /**
     * @param $q
     * @param int $limit
     * @return mixed
     */
    public function autocomplete($q, $limit = 20);


}
