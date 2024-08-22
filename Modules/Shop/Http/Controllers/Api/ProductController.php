<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Shop\Http\Resources\ProductResource;
use Modules\Shop\Http\Resources\ProductShortResource;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class ProductController extends Controller
{


    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $repository;

    /**
     * ProductController constructor.
     * @param ProductRepositoryInterface $repository
     */
    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }


    /**
     * @return AnonymousResourceCollection
     */
    public function build(): AnonymousResourceCollection
    {
        $items = $this->repository->get();
        return ProductResource::collection($items);
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $search = request()->get('search', false);
        $category = request()->get('category', false);
        $limit = request()->get('limit',false);
        $filter = request()->get('filter',false);
        $inStock = request()->get('inStock',false);
        $items = $this->repository->search($search, $category, $limit, $filter,$inStock);
        return ProductShortResource::collection($items);
    }

    /**
     * @param $sku
     * @return ProductResource
     */
    public function show($sku): ProductResource
    {
        $item = $this->repository->get(['sku' => $sku])->first();
        if ($item == null) {
            $item =$this->repository->get(['slug' => $sku])->first();
        }
        return new ProductResource($item);
    }


    /**
     * @param $id
     * @return AnonymousResourceCollection
     */
    public function related($id): AnonymousResourceCollection
    {
        $item = $this->repository->findOrFail($id);
        return ProductShortResource::collection($item->relatedProducts);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function short($id): JsonResponse
    {
        $items = $this->repository->findOrFail($id);
        $data = $items->only('id', 'name', 'price');
        return response()->json([
            'data' => $data
        ]);
    }

}
