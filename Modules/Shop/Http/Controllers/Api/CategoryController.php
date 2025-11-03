<?php


namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Shop\Http\Resources\CategoryResource;
use Modules\Shop\Repositories\Category\CategoryRepositoryInterface;

class CategoryController extends Controller
{

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $repository;

    /**
     * CategoryController constructor.
     * @param CategoryRepositoryInterface $repository
     */
    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $cacheKey = 'all_categories';

        // Cache for 2 minutes (homepage changes rarely)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }else{
//            dd('test');
            $items = $this->repository->get(['parent' => 0,'available'=>true])->sortBy('order');
            $results = CategoryResource::collection($items);
            Cache::put($cacheKey, $results, 3600);
            return $results;
        }

    }

    public function show($slug): CategoryResource
    {
        if ($slug == 'back_in_stock' || $slug == 'new_product'){
            $item = $this->repository->model()->first();
            $item->title = $slug;
            $item->slug = $slug;
            $item->image = null;
        }else{
            $item = $this->repository->model()->where('slug', $slug)->first();
        }

        return new CategoryResource($item);
    }

}
