<?php

namespace Modules\Shop\Repositories\Brand;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Brand;

/**
 * Class BrandRepository
 * @package Modules\Shop\Repositories\Brand
 */
class BrandRepository extends EloquentRepository implements BrandRepositoryInterface
{

    /**
     * @var Brand
     */
    protected $model;

    /**
     * BrandRepository constructor.
     * @param Brand $model
     */
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    /**
     * @inheritDoc
     */
    public function findOrFailBySlug($slug, $with = [])
    {
        // TODO: Replace en with default language.
        return $this->model->with($with)
            ->where('slug', 'like', '%en":"' . $slug . '"%')
            ->firstOrFail();
    }

    public function autocomplete($searchWord, $limit = 20)
    {
        $query = Brand::all();
        $searchWord = str_replace("'", "\'", $searchWord);


        return $query;
    }

    /**
     * @param int $cateLimit
     * @param int $productLimit
     * @param array $wheres
     * @return mixed
     */
//    public function getWithProducts($cateLimit = 9, $productLimit = 9, $wheres = [])
//    {
//
//        $brands = $this->model
//            ->has('products.categories', '>=', $productLimit)
//            ->limit($cateLimit)
//            ->where('parent_id', null)
//            ->where($wheres)
//            ->with('children')
//            ->get();
//        foreach ($brands as $brand)
//            $brand->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit);
//                $q->short();
//            }]);
//        return $brands;
//    }

    /**
     * @param int $cateLimit
     * @param int $productLimit
     * @return mixed
     */
//    public function getBestSellersWithProducts($cateLimit = 4, $productLimit = 9)
//    {
//        $brands = $this->model->limit($cateLimit)
//            ->with('products.categories')
//            ->has('products', '>=', $productLimit)
//            ->limit($cateLimit)
//            ->get();
//        foreach ($brands as $brand)
//            $brand->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit)
//                    ->orderByBestSeller()
//                    ->short();
//            }]);
//        return $brands;
//    }


}
