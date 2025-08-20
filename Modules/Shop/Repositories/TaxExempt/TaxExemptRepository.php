<?php

namespace Modules\Shop\Repositories\TaxExempt;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\TaxExempt;

/**
 * Class TaxExemptRepository
 * @package Modules\Shop\Repositories\TaxExempt
 */
class TaxExemptRepository extends EloquentRepository implements TaxExemptRepositoryInterface
{

    /**
     * @var TaxExempt
     */
    protected $model;

    /**
     * TaxExemptRepository constructor.
     * @param TaxExempt $model
     */
    public function __construct(TaxExempt $model)
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
     * @inheritdoc
     */
    public function autocomplete($q, $limit = 20)
    {
        $query = $this->model
            ->where('name', 'LIKE', '%'.$q.'%')
            ->orWhere('email', 'LIKE', '%'.$q.'%')
            ->orWhere('phone', 'LIKE', '%'.$q.'%')
            ->limit($limit);

//        if ($role) $query->role($role);

        return $query->get();
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
//        foreach ($TaxExempts as $TaxExempt)
//            $TaxExempt->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit);
//                $q->short();
//            }]);
//        return $TaxExempts;
//    }

    /**
     * @param int $cateLimit
     * @param int $productLimit
     * @return mixed
     */
//    public function getBestSellersWithProducts($cateLimit = 4, $productLimit = 9)
//    {
//        $TaxExempts = $this->model->limit($cateLimit)
//            ->with('products.categories')
//            ->has('products', '>=', $productLimit)
//            ->limit($cateLimit)
//            ->get();
//        foreach ($TaxExempts as $TaxExempt)
//            $TaxExempt->load(['products' => function ($q) use ($productLimit) {
//                $q->limit($productLimit)
//                    ->orderByBestSeller()
//                    ->short();
//            }]);
//        return $TaxExempts;
//    }


}
