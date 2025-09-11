<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\Setting;
use Modules\Shop\Http\Resources\ProductResource;
use Modules\Shop\Http\Resources\ProductShortResource;
use Modules\Shop\Http\Resources\ProductShortResourceSearch;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use function OpenTelemetry\API\Logs\setEventName;

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
        $search = request()->get('search', '');
        $category = request()->get('category', '');
        $limit = request()->get('limit', 20);
        $filter = request()->get('filter', '');
        $inStock = request()->get('inStock', false);

        // Handle back in stock category
        if ($category === 'back_in_stock') {
            $items = $this->repository->getBackinStockProducts($limit);
            return ProductShortResource::collection($items);
        }

        $setting = Setting::where('key','search')->first();
//        dd($category);
        if ($setting->value == 'elastic'){
            $items = $this->repository->search($search, $category, $limit, $filter, $inStock);
        }else if ($setting->value == 'normalWithPriority'){
            $items = $this->repository->old_search2($search, $category, $limit, $filter, $inStock);
        }else{
            $items = $this->repository->old_search($search, $category, $limit, $filter, $inStock);
        }

        return ProductShortResource::collection($items);
//        return ProductShortResource::collection($items);
    }

    public function old_index_1(): AnonymousResourceCollection
    {
        $search = request()->get('search', '');
        $category = request()->get('category', '');
        $limit = request()->get('limit', 20);
        $filter = request()->get('filter', false);
        $inStock = request()->get('inStock', false);

        // Validate minimum search length
        if ($search && strlen(trim($search)) < 2) {
            return ProductShortResource::collection([]);
        }

        $items = $this->repository->search($search, $category, $limit, $filter, $inStock);
        return ProductShortResource::collection($items);
    }


    public function old_index(): AnonymousResourceCollection
    {
        $search = request()->get('search', '');
        $category = request()->get('category', '');
        $limit = request()->get('limit', 20);
        $filter = request()->get('filter', false);
        $inStock = request()->get('inStock', false);

        // Validate minimum search length
        if ($search && strlen(trim(preg_replace('/[-_\.,\/()+=]/', '', $search))) < 2) {
            return ProductShortResource::collection([]);
        }

        $items = $this->repository->search($search, $category, $limit, $filter, $inStock);
        return ProductShortResource::collection($items);
    }


//    public function allProducts(): AnonymousResourceCollection
//    {
//dd('test');
//        return ProductShortResource::collection(Product::all());
//    }



    /**
     * @param $sku
     * @return ProductResource
     */
    public function show($sku): ProductResource
    {
        // Attempt to find the product by SKU, including soft-deleted records
        $item = $this->repository->model()->withTrashed()->where('sku', $sku)->first();

        // If not found by SKU, attempt to find it by slug, including soft-deleted records
        if ($item == null) {
            $item = $this->repository->model()->withTrashed()->where('slug', $sku)->first();
        }

        // Return the product resource
        return new ProductResource($item);
    }



    /**
     * @param $id
     * @return AnonymousResourceCollection
     */
    public function related($id): AnonymousResourceCollection
    {
        // Use withTrashed() to include soft-deleted items
        $item = $this->repository->model()->withTrashed()->findOrFail($id);

        // Return related products as a collection
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
