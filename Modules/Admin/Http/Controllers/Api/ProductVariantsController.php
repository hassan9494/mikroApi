<?php

namespace Modules\Admin\Http\Controllers\Api;


use App\Traits\ApiResponser;
use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\DatatableProductVariantResource;
use Modules\Admin\Http\Resources\ProductVariantResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;
use Modules\Shop\Repositories\ProductVariants\ProductVariantsRepository;
use Modules\Shop\Repositories\ProductVariants\ProductVariantsRepositoryInterface;

class ProductVariantsController extends Controller
{

    use ApiResponser;

    /**
     * @var ProductVariantsRepositoryInterface
     */
    private ProductVariantsRepositoryInterface $repository;

    /**
     * ProductController constructor.
     * @param ProductVariantsRepositoryInterface $repository
     */
    public function __construct(ProductVariantsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {

        $data = $this->repository->get();
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function sales(): JsonResponse
    {;
        return Datatable::make($this->repository->model())
            ->with(['completedOrders'])
            ->search('id', 'name', 'sku')
            ->resource(DatatableProductResource::class)
            ->json();
    }

    /**
     * @param $id
     * @return ProductVariantResource
     */
    public function show($id): ProductVariantResource
    {
        $model = $this->repository->findOrFail($id);
        return new ProductVariantResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $id = request('id');
        $where = [
            [
                'product_id',  $id
            ]
        ];
        return Datatable::make($this->repository->model())
            ->search('id', 'name')
            ->resource(DatatableProductVariantResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();
        $data['product_id'] = request('product_id');
        return $this->success(
            $this->repository->create($data)
        );
    }



    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $data = $this->validate();
        return $this->success(
            $this->repository->update($id, $data)
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $this->repository->delete($id);
        return $this->success();
    }

    /**
     * @return JsonResponse
     */
    public function autocomplete(): JsonResponse
    {
        $q = request()->get('q');
        $products = $this->repository->autocomplete($q);
        $response = [];
        foreach ($products as $product)
        {
            $response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->calcPrice(),
                'normal_price' => $product->calcPrice(),
                'min_price' => $product->calcMinPrice(),
                'image' => $product->getFirstMediaUrl(),
                'stock' => $product->stock,
                'sku' => $product->sku
            ];
        }
        return $this->success($response);
    }

    /**
     * @return JsonResponse
     */
    public function stock(): JsonResponse
    {
        $data = request()->validate([
            'products.*.id' => 'exists:products,id',
            'products.*.stock' => 'numeric|min:0',
            'products.*.price.normal_price' => 'numeric|min:0',
            'products.*.price.real_price' => 'numeric|min:0',
            'products.*.price.sale_price' => 'numeric|min:0',
            'products.*.price.distributor_price' => 'numeric|min:0',
        ]);

        foreach ($data['products'] as $item) {
            $this->repository->update(
                $item['id'],
                \Arr::only($item, ['stock', 'price']))
            ;
        }
        return $this->success();
    }

    public function sku(): JsonResponse
    {
        $data = request()->validate([
            'products.*.id' => 'exists:products,id',
            'products.*.stock' => 'numeric|min:0',
            'products.*.brand_id' => 'nullable|numeric|min:0',
            'products.*.source_id' => 'nullable|numeric|min:0',
            'products.*.min_qty' => 'numeric|min:0',
            'products.*.sku' => 'sometimes|string|nullable',
            'products.*.source_sku' => 'sometimes|string|nullable',

        ]);

        foreach ($data['products'] as $item) {
            if ($item['source_sku'] == null){
                $item['source_sku'] = "";
            }
            if ($item['sku'] == null){
                $item['sku'] = "";
            }
            $this->repository->update(
                $item['id'],
                \Arr::only($item, ['stock', 'min_qty', 'sku', 'source_sku','brand_id','source_id']))
            ;
        }
        return $this->success();
    }



//    /**
//     * @return array
//     */
//    private function validate(): array
//    {
//        return request()->validate([
//            'name' => 'required|max:255',
//            'sku' => 'required|max:255',
////            'categories' => 'required',
//            'gallery' => 'nullable',
//            'short_description' => 'nullable',
//            'description' => 'nullable',
//            'features' => 'nullable',
//            'code' => 'nullable',
//            'documents' => 'nullable',
//            'stock' => 'required',
////            'meta' => 'required|array',
//            'price' => 'required',
//            'datasheets' => 'nullable|array',
////            'options.available' => 'required|boolean',
////            'options.featured' => 'required|boolean',
////            'options.kit' => 'required|boolean',
//            'media' => 'nullable|array',
//            'kit' => 'nullable',
//        ]);
//    }

    /**
     * @return array
     */
    private function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'short_description' => 'nullable',
            'stock' => 'required',
            'price' => 'required|array',
            'options.available' => 'required|boolean',
            'options.featured' => 'required|boolean',
            'media' => 'nullable|array',
            'min_qty' => 'required',
            'maxCartAmount' => 'nullable',

        ]);
    }

}
