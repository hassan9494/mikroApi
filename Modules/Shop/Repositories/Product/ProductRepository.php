<?php

namespace Modules\Shop\Repositories\Product;

use App\Repositories\Base\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Shop\Entities\Product;

/**
 * Class EloquentDevice
 * @package App\Repositories\Device
 */
class ProductRepository extends EloquentRepository implements ProductRepositoryInterface
{

    /**
     * @var Product
     */
    protected $model;

    /**
     * ProductRepository constructor.
     * @param Product $model
     */
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function create($data)
    {
        // Extract the ID of the first replacement item if it exists
        if (!empty($data['replacement_item'])) {
            $data['replacement_item'] = $data['replacement_item'][0]['id'];
        } else {
            $data['replacement_item'] = null;
        }
        if ($data['price']['real_price'] == '' || $data['price']['real_price'] == null) {
            $data['price']['real_price'] = $data['price']['normal_price'] * 0.6;
        }

        // Create the model with the modified data
        $data['sku'] = 'me-';
        $model = parent::create($data);
        $model->sku = 'me-' . $model->id;
        $string = Str::replace('.', '-', $model->name);
        $model->slug = Str::slug($string, '-');
        $model->save();

        // Attach categories
        $categories = array_merge($data['categories'], $data['sub_categories'] ?? []);
        $model->categories()->attach($categories);
        $model->relatedProducts()->attach($data['related']);

        // Sync media
        $model->syncMedia($data['media'] ?? []);

        return $model;
    }


    public function update($id, $data)
    {
        // Extract the ID of the first replacement item if it exists
        if (!empty($data['replacement_item'])) {
            $data['replacement_item'] = $data['replacement_item'][0]['id'];
        } else {
            $data['replacement_item'] = null;
        }
        if (!empty($data['sku']) && ($data['sku'] == null || $data['sku'] == '')) {
            $data['sku'] = 'me-' . $id;
        }

        $model = parent::update($id, $data);

        $string = Str::replace('.', '-', $model->name);
        $model->slug = Str::slug($string, '-');
        $model->save();


        if ($data['categories'] ?? false) {
            $categories = array_merge($data['categories'], $data['sub_categories']);
            $model->categories()->sync($categories);
        }


        $related = [];
        if ($data['related'] ?? false) {
            foreach ($data['related'] as $item) {
                $related[$item['id']] = Arr::only($item, '');
            }
        }
        if (count($related) != 0)
            $model->relatedProducts()->sync($related);

        $kit = [];
        if ($data['kit'] ?? false) {
            foreach ($data['kit'] as $item) {
                $kit[$item['id']] = Arr::only($item, 'quantity');
            }
        }
        if (count($kit) != 0)
            $model->kit()->sync($kit);
        $model->syncMedia($data['media'] ?? []);
    }

    /**
     * @param $searchWord
     * @param $category
     * @param int $limit
     * @param $filter
     * @param $inStock
     * @return LengthAwarePaginator
     */
    public function search($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
        $query = Product::query();

        if ($searchWord) {
            $searchTerms = explode(' ', strtolower($searchWord));
            $termCount = count($searchTerms);

            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('name', 'LIKE', '%' . $term . '%');
                }
            });

            // Create ranking logic with a new alias
            $rankingQuery = "CASE ";
            $rankingQuery .= "WHEN LOWER(name) = '" . strtolower($searchWord) . "' THEN " . ($termCount + 1) . " ";
            $rankingQuery .= "ELSE (";
            foreach ($searchTerms as $term) {
                $rankingQuery .= "CASE WHEN LOWER(name) LIKE '%" . $term . "%' THEN 1 ELSE 0 END + ";
            }
            $rankingQuery = rtrim($rankingQuery, "+ ") . ") END AS search_rank"; // Changed here

            // Specify the columns you want to select
            $query->addSelect(['id', 'name', 'sku', 'slug', 'options', 'price', 'is_retired', 'hasVariants', 'replacement_item', 'stock', \DB::raw($rankingQuery)]); // Add other necessary fields

            // Order by the new rank and other criteria
            $query->orderByDesc('search_rank') // Updated here
            ->orderByRaw("CASE WHEN LOWER(name) LIKE '" . strtolower($searchWord) . "%' THEN 0 ELSE 1 END")
                ->orderBy('name');
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

        if ($inStock == 'true') {
            $query->where('stock', '>', 0);
        } else {
            $query->where('stock', '>=', 0);
        }

        return $query->paginate($limit);
    }

    private function getCombinations($array, $size)
    {
        $result = [];
        $combinationsHelper = function ($array, $size, $start = 0, $current = []) use (&$combinationsHelper, &$result) {
            if (sizeof($current) == $size) {
                $result[] = $current;
                return;
            }
            for ($i = $start; $i < sizeof($array); $i++) {
                $new = $current;
                $new[] = $array[$i];
                $combinationsHelper($array, $size, $i + 1, $new);
            }
        };
        $combinationsHelper($array, $size);
        return $result;
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
