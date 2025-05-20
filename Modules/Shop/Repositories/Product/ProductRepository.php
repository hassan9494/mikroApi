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
        // Remove this line.  It is dangerous
        // $searchWord = str_replace("'", "\'", $searchWord);


        // Handle search by name and meta
        if ($searchWord) {
            // Normalize search input
            $normalized = mb_strtolower(trim(preg_replace('/[-_\.,\/()+=]/', ' ', $searchWord)));
            $searchTerms = array_filter(explode(' ', $normalized));
            $termCount = count($searchTerms);

            if ($termCount === 0) {
                return $query->paginate($limit);
            }

            // Build complex ranking
            $query->select([
                'products.*',
                \DB::raw("
                (CASE
                    WHEN LOWER(name) = '{$normalized}' THEN 1000
                    WHEN LOWER(name) LIKE '{$normalized}%' THEN 900
                    WHEN LOWER(name) LIKE '% {$normalized}%' THEN 800
                    ELSE
                        (".implode(' + ', array_map(function($term) {
                        return "(CASE
                                WHEN LOWER(name) LIKE '%{$term}%' THEN 100
                                WHEN meta_title LIKE '%{$term}%' THEN 80
                                WHEN meta_keywords LIKE '%{$term}%' THEN 60
                                WHEN meta_description LIKE '%{$term}%' THEN 40
                                ELSE 0
                            END)";
                    }, $searchTerms)).")
                END) as search_rank
            ")
            ]);

            // Add WHERE conditions
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('name', 'LIKE', "%{$term}%")
                        ->orWhere('meta_title', 'LIKE', "%{$term}%")
                        ->orWhere('meta_keywords', 'LIKE', "%{$term}%")
                        ->orWhere('meta_description', 'LIKE', "%{$term}%");
                }
            });

            $query->orderByDesc('search_rank')
                ->orderByRaw("CASE WHEN LOWER(name) LIKE '{$normalized}%' THEN 0 ELSE 1 END")
                ->orderBy('name');
        }
        elseif ($category) {
            // Search by category if no search word is provided
            if ($category == 'new_product'){
                $latestIds = Product::query()
                    ->orderBy('id', 'desc')
                    ->take(720)
                    ->pluck('id');

                // Then filter by these IDs
                $query->whereIn('id', $latestIds)
                    ->orderBy('id', 'desc');

            }else{
                $query->whereHas('categories', function (Builder $q) use ($category) {
                    $q->where('slug', $category);
                });
            }

        } else {
            // Default to featured products if no category or search word is provided
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

        // Check stock availability based on the parameter
        if ($inStock === true) {
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


    public function autocomplete2($searchWord, $limit = 20)
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

    public function autocomplete($searchWord, $limit = 20)
    {
        $query = Product::query();
        $searchWord = str_replace("'", "\'", $searchWord);

        // Handle search by name and meta
        if ($searchWord) {
            $searchTerms = explode(' ', strtolower($searchWord));
            $termCount = count($searchTerms);

            // Build the query for name and meta search
            $query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('name', 'LIKE', '%' . $term . '%')
                    ->orWhere('sku', 'LIKE', '%' . $term . '%')
                    ->orWhere('source_sku', 'LIKE', '%' . $term . '%')
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.title')) LIKE ?", ['%' . $term . '%'])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.keywords')) LIKE ?", ['%' . $term . '%'])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.description')) LIKE ?", ['%' . $term . '%']);
                }
            });

            // Create ranking logic with a new alias
            $rankingQuery = "CASE
                            WHEN LOWER(name) = '" . strtolower($searchWord) . "' THEN " . ($termCount + 1) . "
                            ELSE (";

            foreach ($searchTerms as $term) {
                $rankingQuery .= "CASE
                                WHEN LOWER(name) LIKE '%" . $term . "%' THEN 1
                                WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.title')) LIKE '%" . $term . "%' THEN 1
                                WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.keywords')) LIKE '%" . $term . "%' THEN 1
                                WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.description')) LIKE '%" . $term . "%' THEN 1
                                ELSE 0 END + ";
            }

            $rankingQuery = rtrim($rankingQuery, "+ ") . ") END AS search_rank";

            // Specify the columns you want to select
            $query->addSelect(['id', 'name', 'sku', 'slug','location', 'options', 'source_sku','price', 'is_retired', 'hasVariants', 'replacement_item', 'stock', \DB::raw($rankingQuery)]);

            // Order by the new rank and other criteria
            $query->orderByDesc('search_rank')
                ->orderByRaw("CASE WHEN LOWER(name) LIKE '" . strtolower($searchWord) . "%' THEN 0 ELSE 1 END")
                ->orderBy('name');
        }

        return $query->paginate($limit);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {

        return $this->findOrFail($id)->delete();
    }

    /**
     * @inheritdoc
     */
    public function restore($id)
    {

        $product = $this->model->onlyTrashed()->findOrFail($id);

        // Restore the soft-deleted record
        return $product->restore();
    }


}
