<?php

namespace Modules\Shop\Repositories\ProductVariants;

use App\Repositories\Base\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;

/**
 * Class EloquentDevice
 * @package App\Repositories\Device
 */
class ProductVariantsRepository extends EloquentRepository implements ProductVariantsRepositoryInterface
{

    /**
     * @var ProductVariant
     */
    protected $model;

    /**
     * ProductRepository constructor.
     * @param ProductVariant $model
     */
    public function __construct(ProductVariant $model)
    {
        parent::__construct($model);
    }

    public function create($data)
    {
        $product = Product::find($data['color_id']);
        $parentProduct = Product::find($data['product_id']);
        $parentProduct->colors_nick_names .= ' , ' . $data['name'] . ' , ' . $product->name;

        $product->is_show_for_search = false;

//        $parentProduct->colors_nick_names = $parentProduct->colors_nick_names + $data['name'] + $product->name;
        $data['short_description'] = $product->short_description;
        $data['short_description'] = $product->short_description;
        $data['price'] = $product->price;
        $data['stock'] = $product->stock;
        $option['available'] =$product->options->available;
        $option['featured'] =$product->options->featured;
        $data['options'] = $option;
        $data['listPriority'] = $product->listPriority;
        $data['maxCartAmount'] = $product->maxCartAmount;
        $data['min_qty'] = $product->min_qty;
        $data['is_retired'] = $product->is_retired;
        $data['source'] = $product->source;
        $data['location'] = $product->location;

        // Create the model with the modified data
        $model = parent::create($data);
        $parentProduct->save();
        $product->save();
        // Attach categories

        return $model;
    }


    public function update($id, $data)
    {
        $color = ProductVariant::find($id);
        $oldProduct = Product::find($color->color_id);
        $product = Product::find($data['color_id']);
        $parentProduct = Product::find($color->product_id);

// Convert current names to array and remove empty values
        $currentNames = array_filter(array_map('trim', explode(',', $parentProduct->colors_nick_names)));

// Remove old names if they exist
        $currentNames = array_filter($currentNames, function($name) use ($color, $oldProduct) {
            return $name !== $color->name && $name !== $oldProduct->name;
        });

// Add new names
        $currentNames[] = $data['name'];
        $currentNames[] = $product->name;

// Remove duplicates and update
        $parentProduct->colors_nick_names = implode(' , ', array_unique($currentNames));
        $data['short_description'] = $product->short_description;
        $data['short_description'] = $product->short_description;
        $data['price'] = $product->price;
        $data['stock'] = $product->stock;
        $option['available'] =$product->options->available;
        $option['featured'] =$product->options->featured;
        $data['options'] = $option;
        $data['listPriority'] = $product->listPriority;
        $data['maxCartAmount'] = $product->maxCartAmount;
        $data['min_qty'] = $product->min_qty;
        $data['is_retired'] = $product->is_retired;
        $data['source'] = $product->source;
        $data['location'] = $product->location;
        $model = parent::update($id, $data);
        $parentProduct->save();
        $product->save();
        return $model;
    }

    /**
     * @param $searchWord
     * @param $category
     * @param int $limit
     * @param $filter
     * @param $inStock
     * @return LengthAwarePaginator
     */
    public function search($searchWord, $category, $limit = 20, $filter,$inStock = false)
    {
        $query = ProductVariant::query();

        if ($searchWord) {
            return ProductVariant::search($searchWord)->paginate($limit);
        } else if ($category) {
            $query->whereHas('categories', function (Builder $q) use ($category) {
                $q->where('slug', $category);
            });
        } else {
            $query->where(['options->featured' => true]);
        }

        // Apply filter based on the selected option
        switch ($filter) {
            case 'top-sale':
                $query->withCount(['completedOrders as sales_count' => function ($query) {
                    $query->select(\DB::raw('SUM(order_products.quantity)'));
                }])->orderBy('sales_count', 'desc');
                break;
            case 'new-item':
                $query->orderBy('id', 'desc');
                break;
            case 'old-item':
                $query->orderBy('id', 'asc');
                break;
            case 'sale':
                $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) > 0');
                break;
            case 'price-high':
                $query->orderByRaw('
                CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) = "0"
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END DESC
            ');
                break;
            case 'price-low':
                $query->orderByRaw('
                CASE
                    WHEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) = "0"
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END ASC
            ');
                break;
            default:
                // No filter applied
                break;
        }

        if ($inStock == 'true'){
            $query->where('stock','>',0);
        }else{
            $query->where('stock','>=',0);
        }
        return $query->paginate($limit);
    }



    public function autocomplete($searchWord, $limit = 20)
    {

        $inpStrArray = explode(" ", $searchWord); #converting string to array
        $revArr = array_reverse($inpStrArray); #reversing the array
        $revStr = implode(" ", $revArr); #joining the array back to string

        return $this->model->where('sku', 'like', "%$searchWord%")
            ->orWhere('name', 'like', "%$searchWord%")
            ->orWhere('short_description', 'like', "%$searchWord%")
            ->orwhere('sku', 'like', "%$revStr%")
            ->orwhere('name', 'like', "%$revStr%")
            ->orwhere('short_description', 'like', "%$revStr%")
            ->limit($limit)
            ->get();
    }

}
