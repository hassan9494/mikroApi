<?php


namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Routing\Controller;
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
        $items = $this->repository->get(['parent' => 0,'available'=>true])->sortBy('order');
        return CategoryResource::collection($items);
    }

}
