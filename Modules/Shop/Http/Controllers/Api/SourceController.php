<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Shop\Repositories\Source\SourceRepositoryInterface;

class SourceController extends Controller
{
    /**
     * @var SourceRepositoryInterface
     */
    private SourceRepositoryInterface $repository;

    /**
     * CategoryController constructor.
     * @param SourceRepositoryInterface $repository
     */
    public function __construct(SourceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $items = $this->repository->get()->sortBy('order');
        return SourceResource::collection($items);
    }
}
