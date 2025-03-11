<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Models\OldProduct;
use App\Traits\ApiResponser;
use App\Traits\Datatable;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\ProductResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class ProductController extends Controller
{

    use ApiResponser;

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
     * @return ProductResource
     */
    public function show($id): ProductResource
    {
        $model = $this->repository->findOrFail($id, ['kit']);
        return new ProductResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->custom_search('id', 'name', 'sku','meta')
            ->resource(DatatableProductResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function deletedDatatable()
    {
        $products = Product::onlyTrashed()->get();
        $total = Product::onlyTrashed()->count();
        $items = ProductResource::collection($products);
        return ['data'=>['items'=>$items,'total'=>$total]];
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();
        return $this->success(
            $this->repository->create($data)
        );
    }


    /**
     * @return JsonResponse
     */
    public function storeFromDataBase()
    {
        $product = Product::find(88);
//        foreach ($oldProducts as $product){

            $media = [];
            $x = request();
            $x['name'] = $product->name;
            $x['sku'] = $product->sku;
            $x['short_description'] = $product->short_description;
            $x['description'] = $product->description;
            $x['features'] = $product->features;
            $x['code'] = $product->code;
            $x['documents'] = $product->documents;
            $x['stock'] = $product->stock;
            $x['meta'] = $product->meta;
            $x['price'] = $product->price;
            $x['datasheets'] = $product->datasheets;
            $x['price'] = $product->price;
            $x['options'] = $product->options;

            $x['gallery'] = $product->gallery;

            $test = str_replace('[', '', $product->gallery);
            $test2 = str_replace(']', '', $test);
            foreach (explode(',', $test2) as $key=>$item){
                $media[$key] = [
                    'id' => $item, 'key' => "temp/".str_replace('"', '', $item), 'new' => true,'url' =>"/storage/temp/".str_replace('"', '', $item)
                ];

//            }
//            $x['media'] = $media;
            $data = $this->validate();
            $this->repository->update($product->id, $data);
        }
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
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function restore($id): JsonResponse
    {

        $this->repository->restore($id);
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
                'sku' => $product->sku,
                'source_sku' => $product->source_sku,
                'location' => $product->location,
                'purchases_price'=>(float)$product->price->real_price,
                'normal'=>(float)$product->price->normal_price,
                'sale_price'=>(float)$product->price->sale_price
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

    public function stock2(): JsonResponse
    {
        $data = request()->validate([
            'products.*.id' => 'exists:products,id',
            'products.*.min_qty' => 'numeric|min:0',
            'products.*.order_qty' => 'numeric|nullable',
            'products.*.stock_available' => 'numeric|nullable',
            'products.*.store_available' => 'numeric|nullable',
            'products.*.location' => 'sometimes|string|nullable',

        ]);

        foreach ($data['products'] as $item) {

            $this->repository->update(
                $item['id'],
                \Arr::only($item, ['order_qty', 'min_qty', 'stock_available', 'store_available','location']))
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
            'sku' => 'nullable|max:255',
            'categories' => 'required',
            'sub_categories' => 'nullable',
            //'gallery' => 'nullable',
            'short_description' => 'nullable',
            'description' => 'nullable',
            'packageInclude' => 'nullable',
            'location' => 'nullable',
            'features' => 'nullable',
            'code' => 'nullable',
            'documents' => 'nullable',
            'stock' => 'required',
            'meta' => 'required|array',
            'price' => 'required|array',
            'datasheets' => 'nullable|array',
            'options.available' => 'required|boolean',
            'options.featured' => 'required|boolean',
            'options.kit' => 'required|boolean',
            'media' => 'nullable|array',
            'kit' => 'nullable|array',
            'related' => 'nullable|array',
            'source_sku' => 'nullable|max:255',
            'min_qty' => 'required',
            'maxCartAmount' => 'nullable',
            'brand_id' => 'nullable|integer',
            'source_id' => 'nullable|integer',
            'is_retired' => 'nullable|boolean',
            'replacement_item' => 'nullable|array',
            'hasVariants' => 'nullable|boolean',

        ]);
    }

}
