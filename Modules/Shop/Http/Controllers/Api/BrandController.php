<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shop\Http\Resources\BrandResource;
use Modules\Shop\Repositories\Brand\BrandRepositoryInterface;

class BrandController extends Controller
{
    /**
     * @var BrandRepositoryInterface
     */
    private BrandRepositoryInterface $repository;

    /**
     * CategoryController constructor.
     * @param BrandRepositoryInterface $repository
     */
    public function __construct(BrandRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $items = $this->repository->get()->sortBy('order');
        return BrandResource::collection($items);
    }
}
