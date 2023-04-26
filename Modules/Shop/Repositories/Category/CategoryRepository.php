<?php

namespace Modules\Shop\Repositories\Category;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Category;

/**
 * Class CategoryRepository
 * @package Modules\Shop\Repositories\Category
 */
class CategoryRepository extends EloquentRepository implements CategoryRepositoryInterface
{

    /**
     * @var Category
     */
    protected $model;

    /**
     * CategoryRepository constructor.
     * @param Category $model
     */
    public function __construct(Category $model)
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
    public function getWithProducts($cateLimit = 9, $productLimit = 9, $wheres = [])
    {

        $categories = $this->model
            ->has('products.categories', '>=', $productLimit)
            ->limit($cateLimit)
            ->where('parent_id', null)
            ->where($wheres)
            ->with('children')
            ->get();
        foreach ($categories as $category)
            $category->load(['products' => function ($q) use ($productLimit) {
                $q->limit($productLimit);
                $q->short();
            }]);
        return $categories;
    }

    /**
     * @param int $cateLimit
     * @param int $productLimit
     * @return mixed
     */
    public function getBestSellersWithProducts($cateLimit = 4, $productLimit = 9)
    {
        $categories = $this->model->limit($cateLimit)
            ->with('products.categories')
            ->has('products', '>=', $productLimit)
            ->limit($cateLimit)
            ->get();
        foreach ($categories as $category)
            $category->load(['products' => function ($q) use ($productLimit) {
                $q->limit($productLimit)
                    ->orderByBestSeller()
                    ->short();
            }]);
        return $categories;
    }


}
