<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
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
    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $token = request()->bearerToken();

        if ($token) {
            // Find the token and authenticate the user
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                Auth::login($user);
            }
        }
        $user = auth()->user();
        $search = request()->get('search', '');
        $category = request()->get('category', '');
        $limit = request()->get('limit', 20);
        $filter = request()->get('filter', '');
        $inStock = request()->get('inStock', false);
        $page = request()->get('page', 1);

        if ($search && strlen(trim($search)) < 2) {
            return ProductShortResource::collection([]);
        }

        // Cache ONLY the homepage request (empty category, first page, default filters)
        $isHomepageRequest = empty($search) && empty($category) && $page == 1 &&
            $limit == 24 && empty($filter) && $inStock == false;

        if ($isHomepageRequest) {
            $cacheKey = 'homepage_products';

            // Cache for 2 minutes (homepage changes rarely)
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
        }

        // Handle back in stock category
        if ($category === 'back_in_stock') {
            $query = $this->repository->getBackinStockProductsQuery();

            // Apply filters
            switch ($filter) {
                case 'new-item':
                    $query->orderBy('products.id', 'desc');
                    break;
                case 'old-item':
                    $query->orderBy('products.id', 'asc');
                    break;
                case 'price-high':
                    $query->orderByRaw('
                CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.sale_price")) = "0"
                    THEN JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.sale_price"))
                END DESC
            ');
                    break;
                case 'price-low':
                    $query->orderByRaw('
                CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.sale_price")) = "0"
                    THEN JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.sale_price"))
                END ASC
            ');
                    break;
                case 'sale':
                    $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(products.price, "$.sale_price")) > 0');
                // Fall through to default order for sale items
                default:
                    $query->orderBy('latest_invoice.latest_date', 'DESC');
                    break;
            }

            // Apply in-stock filter
            if ($inStock === true) {
                $query->where('products.stock', '>', 0);
            }

            $items = $query->paginate($limit);
            $result = ProductShortResource::collection($items);

            // Cache if it's the homepage request
            if ($isHomepageRequest) {
                Cache::put($cacheKey, $result, 120); // 2 minutes
            }

            return $result;
        }

        // Only cache the setting lookup (safe - won't cause storage issues)
        $setting = Cache::remember('search_setting', 3600, function () {
            return Setting::where('key', 'search')->first();
        });

        if ($setting->value == 'elastic') {
            $items = $this->repository->search($search, $category, $limit, $filter, $inStock);
        } else if ($setting->value == 'normalWithPriority') {
            $items = $this->repository->search_with_priorities($search, $category, $limit, $filter, $inStock, $user);
        } else {
            $items = $this->repository->simple_search($search, $category, $limit, $filter, $inStock);
        }

        $result = ProductShortResource::collection($items);

        // Cache if it's the homepage request
        if ($isHomepageRequest) {
            Cache::put($cacheKey, $result, 120); // 2 minutes
        }

        return $result;
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

        // Limit related products to 15
        $relatedProducts = $item->relatedProducts()->take(20)->get();

        // Return related products as a collection
        return ProductShortResource::collection($relatedProducts);
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
