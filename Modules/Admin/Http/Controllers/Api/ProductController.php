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
use Modules\Admin\Http\Resources\ProductVariantResource;
use Modules\Shop\Entities\Product;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Illuminate\Http\Request;

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
    public function kitDatatable()
    {
        $products = Product::where('options->kit',true)->paginate(request('limit'));
        $total = Product::where('options->kit',true)->count();
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
        $x['short_description_ar'] = $product->short_description_ar;
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
//        return $this->success();

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
                'stock_available' => $product->stock_available,
                'store_available' => $product->store_available,
                'sku' => $product->sku,
                'source_sku' => $product->source_sku,
                'location' => $product->location,
                'purchases_price'=>(float)$product->price->real_price,
                'base_purchases_price'=>(float)$product->base_purchases_price,
                'exchange_factor'=>(float)$product->exchange_factor,
                'distributer_price'=>(float)$product->price->distributor_price,
                'normal'=>(float)$product->price->normal_price,
                'sale_price'=>(float)$product->price->sale_price,
                'colors' => ProductVariantResource::collection($product->product_variants),
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
            $product = Product::find($item['id']);

            // Get current stock values
            $currentStoreAvailable = $product->store_available ?? 0;
            $currentStockAvailable = $product->stock_available ?? 0;
            $currentTotal = $product->stock ?? ($currentStoreAvailable + $currentStockAvailable);

            // Get the new total stock
            $newTotal = (int)$item['stock'];
            $difference = $newTotal - $currentTotal;

            if ($difference > 0) {
                // Stock increase: add to store_available first
                $product->store_available = $currentStoreAvailable + $difference;
                $product->stock_available = $currentStockAvailable;
            } elseif ($difference < 0) {
                // Stock decrease: remove from store_available first, then stock_available
                $decreaseAmount = abs($difference);

                // First, try to take from store_available
                $fromStore = min($decreaseAmount, $currentStoreAvailable);
                $remainingDecrease = $decreaseAmount - $fromStore;

                // Then, take from stock_available if needed
                $fromStock = min($remainingDecrease, $currentStockAvailable);

                $product->store_available = $currentStoreAvailable - $fromStore;
                $product->stock_available = $currentStockAvailable - $fromStock;
            } else {
                // No change
                $product->store_available = $currentStoreAvailable;
                $product->stock_available = $currentStockAvailable;
            }

            // Update total stock
            $product->stock = $product->store_available + $product->stock_available;

            // Update price if provided
            if (isset($item['price'])) {
                $product->price = $item['price'];
            }

            $product->save();
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
            'products.*.stock_location' => 'sometimes|string|nullable',
            'products.*.location' => 'sometimes|string|nullable',
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
                \Arr::only($item, ['stock', 'stock_location', 'location', 'sku', 'source_sku','brand_id','source_id']))
            ;
        }
        return $this->success();
    }

    public function stock2(): JsonResponse
    {
        $data = request()->validate([
            'products.*.id' => 'exists:products,id',
            'products.*.min_qty' => 'numeric|min:0',
            'products.*.purchases_qty' => 'numeric|min:0',
            'products.*.order_qty' => 'numeric|nullable',
            'products.*.stock_available' => 'numeric|nullable',
            'products.*.store_available' => 'numeric|nullable',

        ]);

        foreach ($data['products'] as $item) {

            $this->repository->update(
                $item['id'],
                \Arr::only($item, ['order_qty', 'min_qty', 'stock_available', 'store_available','purchases_qty']))
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
            'short_description_ar' => 'nullable',
            'casher_note' => 'nullable',
            'description' => 'nullable',
            'packageInclude' => 'nullable',
            'location' => 'nullable',
            'stock_location' => 'nullable',
            'features' => 'nullable',
            'code' => 'nullable',
            'documents' => 'nullable',
            'stock' => 'required|integer|min:0',
            'stock_available' => 'nullable|integer|min:0',
            'store_available' => 'nullable|integer|min:0',
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
            'order_qty' => 'numeric|nullable',
            'exchange_factor' => 'numeric|nullable',
            'base_purchases_price' => 'numeric|nullable',
            'search_factor' => 'numeric|nullable',
            'maxCartAmount' => 'nullable',
            'brand_id' => 'nullable|integer',
            'source_id' => 'nullable|integer',
            'is_retired' => 'nullable|boolean',
            'replacement_item' => 'nullable|array',
            'hasVariants' => 'nullable|boolean',
            'is_show_for_search' => 'nullable|boolean',
            'is_color_sun' => 'nullable|boolean',

        ]);
    }

    public function stock3(Request $request)
    {
        $products = $request->get('products');

        foreach ($products as $productData) {
            $product = Product::find($productData['id']);
            if ($product) {
                // Update the fields that are specific to stock3
                if (isset($productData['min_qty'])) {
                    $product->min_qty = $productData['min_qty'];
                }
                if (isset($productData['order_qty'])) {
                    $product->order_qty = $productData['order_qty'];
                }
                if (isset($productData['source_id'])) {
                    $product->source_id = $productData['source_id'];
                }
                if (isset($productData['location'])) {
                    $product->location = $productData['location'];
                }
                if (isset($productData['stock_location'])) {
                    $product->stock_location = $productData['stock_location'];
                }
                if (isset($productData['stock'])) {
                    $product->stock = $productData['stock'];
                }

                $product->save();
            }
        }

        return response()->json(['message' => 'Updated successfully']);
    }
    public function adjustStock(Request $request, $id): JsonResponse
    {
        $request->validate([
            'stock_available' => 'required|integer|min:0',
            'store_available' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($id);

        $product->update([
            'stock_available' => $request->stock_available,
            'store_available' => $request->store_available,
            'stock' => $request->stock_available + $request->store_available,
        ]);

        return $this->success([
            'message' => 'Stock distribution updated successfully',
            'product' => new ProductResource($product),
        ]);
    }

    /**
     * Transfer stock between locations
     */
    public function transferStock(Request $request, $id): JsonResponse
    {
        $request->validate([
            'from' => 'required|in:stock_available,store_available',
            'to' => 'required|in:stock_available,store_available',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($id);

        $fromField = $request->from;
        $toField = $request->to;
        $quantity = $request->quantity;

        // Check if there's enough stock in the source location
        if ($product->$fromField < $quantity) {
            return $this->error('Not enough stock in the source location', 400);
        }

        // Transfer stock
        $product->update([
            $fromField => $product->$fromField - $quantity,
            $toField => $product->$toField + $quantity,
            'stock' => $product->stock_available + $product->store_available,
        ]);

        return $this->success([
            'message' => 'Stock transferred successfully',
            'product' => new ProductResource($product),
        ]);
    }
}
