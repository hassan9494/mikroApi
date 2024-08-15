<?php

namespace Modules\Shop\Repositories\Source;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Source;

/**
 * Class SourceRepository
 * @package Modules\Shop\Repositories\Source
 */
class SourceRepository extends EloquentRepository implements SourceRepositoryInterface
{

    /**
     * @var Source
     */
    protected $model;

    /**
     * SourceRepository constructor.
     * @param Source $model
     */
    public function __construct(Source $model)
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
//        foreach ($Sources as $Source)
//            $Source->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit);
//                $q->short();
//            }]);
//        return $Sources;
//    }

    /**
     * @param int $cateLimit
     * @param int $productLimit
     * @return mixed
     */
//    public function getBestSellersWithProducts($cateLimit = 4, $productLimit = 9)
//    {
//        $Sources = $this->model->limit($cateLimit)
//            ->with('products.categories')
//            ->has('products', '>=', $productLimit)
//            ->limit($cateLimit)
//            ->get();
//        foreach ($Sources as $Source)
//            $Source->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit)
//                    ->orderByBestSeller()
//                    ->short();
//            }]);
//        return $Sources;
//    }


}
