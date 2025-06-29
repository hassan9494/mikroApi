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
        $client = app('elasticsearch');
        $page = request()->get('page', 1);
        $from = ($page - 1) * $limit;
        $searchWord = trim($searchWord);

        // Handle empty search - return all products
        if (empty($searchWord)) {
            $body = [
                'from' => $from,
                'size' => $limit,
                'track_total_hits' => true,
                'query' => ['match_all' => new \stdClass()],
                'sort' => [['created_at' => 'desc']]
            ];

            // Handle category filter for empty search
            if ($category && $category !== 'new_product') {
                $body['query'] = [
                    'bool' => [
                        'filter' => [['term' => ['category_slugs' => $category]]]
                    ]
                ];
            }

            return $this->executeSearch($client, $body, $limit, $page);
        }

        // Preprocessing for non-empty search
        $cleanQuery = preg_replace('/[-\/\\\\]/', ' ', $searchWord);
        $cleanQuery = preg_replace('/[^\p{L}\p{N}\s]/u', '', $cleanQuery);
        $cleanQuery = mb_strtolower(trim(preg_replace('/\s+/', ' ', $cleanQuery)));

        if (strlen($cleanQuery) < 2) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $limit);
        }

        // Build should clauses
        $shouldClauses = [
            // ID exact match
            [
                'term' => [
                    'id' => [
                        'value' => $cleanQuery,
                        'boost' => 10
                    ]
                ]
            ],
            // Exact phrase match
            [
                'multi_match' => [
                    'query' => $cleanQuery,
                    'type' => 'phrase',
                    'fields' => [
                        'name^5',
                        'sku^5',
                        'source_sku^5',
                        'location.keyword^5'
                    ],
                    'boost' => 6
                ]
            ],
            // All words in any order
            [
                'multi_match' => [
                    'query' => $cleanQuery,
                    'type' => 'cross_fields',
                    'operator' => 'and',
                    'fields' => [
                        'name^4',
                        'sku^4',
                        'source_sku^4',
                        'location^4'
                    ],
                    'boost' => 5
                ]
            ],
            // Partial match in order
            [
                'match_phrase' => [
                    'name.ngram' => [
                        'query' => $cleanQuery,
                        'slop' => 5,
                        'boost' => 4
                    ]
                ]
            ],
            // Any 2+ words
            [
                'multi_match' => [
                    'query' => $cleanQuery,
                    'minimum_should_match' => '2<70%',
                    'fields' => [
                        'name^3',
                        'sku^3',
                        'source_sku^3',
                        'location^3',
                        'location.ngram^3',
                        'meta_title^2',
                        'meta_keywords',
                        'meta_description',
                        'category_slugs',
                        'short_description'
                    ],
                    'boost' => 3
                ]
            ],
            // Any word
            [
                'multi_match' => [
                    'query' => $cleanQuery,
                    'fields' => [
                        'name^2',
                        'sku^2',
                        'source_sku^2',
                        'location^2',
                        'location.ngram^2',
                        'meta_title',
                        'meta_keywords',
                        'meta_description',
                        'category_slugs',
                        'short_description'
                    ],
                    'boost' => 2
                ]
            ],
            // Category match
            [
                'term' => [
                    'category_slugs' => [
                        'value' => $cleanQuery,
                        'boost' => 1
                    ]
                ]
            ]
        ];

        $body = [
            'from' => $from,
            'size' => $limit,
            'track_total_hits' => true,
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'should' => $shouldClauses,
                            'minimum_should_match' => 1 // Require at least one match
                        ]
                    ],
                    // FIXED: Replaced field_value_factor with script_score
                    'functions' => [
                        [
                            'script_score' => [
                                'script' => [
                                    'source' => "def stock = doc['stock'].value; if (stock < 0) { return 0.0; } else { return Math.log1p(stock * 0.1); }"
                                ]
                            ]
                        ]
                    ],
                    'boost_mode' => 'sum'
                ]
            ]
        ];

        // Handle category filter
        if ($category && $category !== 'new_product') {
            $body['query']['function_score']['query']['bool']['filter'][] = [
                'term' => ['category_slugs' => $category]
            ];
        }

        // Handle in-stock filter
        if ($inStock) {
            $body['query']['function_score']['query']['bool']['filter'][] = [
                'range' => ['stock' => ['gt' => 0]]
            ];
        }

        // Handle sorting
        $sort = [];

        // Push 0-stock items to the end
        $sort[] = [
            '_script' => [
                'type' => 'number',
                'script' => [
                    'lang' => 'painless',
                    'source' => "doc['stock'].value == 0 ? 1 : 0"
                ],
                'order' => 'asc'
            ]
        ];

        if ($category === 'new_product') {
            $sort[] = ['created_at' => 'desc'];
            $body['size'] = min($limit * 25, 500);
        } else {
            switch ($filter) {
                case 'new-item': $sort[] = ['created_at' => 'desc']; break;
                case 'old-item': $sort[] = ['created_at' => 'asc']; break;
                case 'price-high': $sort[] = ['effective_price' => 'desc']; break;
                case 'price-low': $sort[] = ['effective_price' => 'asc']; break;
                case 'sale':
                    $body['query']['function_score']['query']['bool']['filter'][] = [
                        'range' => ['sale_price' => ['gt' => 0]]
                    ];
                    $sort[] = ['_score' => 'desc'];
                    break;
                default:
                    $sort[] = ['_score' => 'desc'];
            }
        }

        // Add stock sorting
        $sort[] = ['stock' => 'desc'];
        $body['sort'] = $sort;

        return $this->executeSearch($client, $body, $limit, $page, $searchWord, $category, $filter, $inStock);
    }

    private function executeSearch($client, $body, $limit, $page, $searchWord = '', $category = '', $filter = '', $inStock = false)
    {
        try {
            $response = $client->search([
                'index' => 'test_products',
                'body' => $body
            ]);

            $total = $response['hits']['total']['value'];
            $ids = collect($response['hits']['hits'])->pluck('_id')->all();

            $products = Product::with('categories', 'media')
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(function ($product) use ($ids) {
                    return array_search($product->id, $ids);
                });

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $products,
                $total,
                $limit,
                $page
            );
        } catch (\Exception $e) {
            dd($e);
            \Log::error('Elasticsearch error: '.$e->getMessage());
            return $this->old_search($searchWord, $category, $limit, $filter, $inStock);
        }
    }
    public function old_elastic_search($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
        $client = app('elasticsearch');
        $page = request()->get('page', 1);
        $from = ($page - 1) * $limit;

        // Build base query
        $body = [
            'from' => $from,
            'size' => $limit,
            'track_total_hits' => true,
        ];

        // Handle search query
        if ($searchWord) {
            $body['query'] = [
                'multi_match' => [
                    'query' => $searchWord,
                    'fields' => [
                        'name^3',
                        'meta_title^2',
                        'meta_keywords',
                        'meta_description',
                        'sku',
                        'source_sku',
                        'categories',
                        'category_slugs'
                    ],
                    'fuzziness' => 'AUTO'
                ]
            ];
        }
        // Handle category filter
        elseif ($category) {
            if ($category == 'new_product') {
                $body['sort'] = [['created_at' => 'desc']];
                $body['size'] = min($limit * 25, 500);
            } else {
                // Use the new category_slugs field
                $body['query'] = [
                    'term' => ['category_slugs' => $category]
                ];
            }
        }

            // Default to featured
        else {
            $body['query'] = [
                'term' => ['featured' => true]
            ];
        }

        // Add stock filter
        $filters = [];
        if ($inStock === true) {
            $filters[] = ['range' => ['stock' => ['gt' => 0]]];
        }

        // Apply filters
        if (!empty($filters)) {
            if (isset($body['query'])) {
                $body['query'] = [
                    'bool' => [
                        'must' => $body['query'],
                        'filter' => $filters
                    ]
                ];
            } else {
                $body['query'] = [
                    'bool' => [
                        'filter' => $filters
                    ]
                ];
            }
        }

        // Apply sorting
        switch ($filter) {
            case 'new-item':
                $body['sort'] = [['created_at' => 'desc']];
                break;
            case 'old-item':
                $body['sort'] = [['created_at' => 'asc']];
                break;
            case 'price-high':
                $body['sort'] = [['effective_price' => 'desc']];
                break;
            case 'price-low':
                $body['sort'] = [['effective_price' => 'asc']];
                break;
            case 'sale':
                if (isset($body['query']['bool'])) {
                    $body['query']['bool']['filter'][] = ['range' => ['sale_price' => ['gt' => 0]]];
                } else {
                    $body['query'] = [
                        'range' => ['sale_price' => ['gt' => 0]]
                    ];
                }
                break;
        }

        // Execute search
        $response = $client->search([
            'index' => 'test_products',
            'body' => $body
        ]);

        // Process results
        $total = $response['hits']['total']['value'];
        $ids = collect($response['hits']['hits'])->pluck('_id')->all();

        // Get products from database
        $products = Product::with('categories', 'media')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(function ($product) use ($ids) {
                return array_search($product->id, $ids);
            });

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $products,
            $total,
            $limit,
            $page
        );
    }



    public function old_search($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
        $query = Product::query();
        // Remove this line.  It is dangerous
        // $searchWord = str_replace("'", "\'", $searchWord);


        // Handle search by name and meta
        if ($searchWord) {
            // Decode URL-encoded characters first
            $decoded = urldecode($searchWord);

            // Normalize without removing special chars but escape them
            $normalized = trim(preg_replace('/\s+/', ' ', $decoded));
            $searchTerms = array_filter(explode(' ', $normalized));
            $termCount = count($searchTerms);

            if ($termCount === 0) {
                return $query->paginate($limit);
            }

            // Escape special characters for SQL
            $escapedNormalized = addslashes($normalized);
            $escapedTerms = array_map('addslashes', $searchTerms);

            // Build the CASE statement with escaped values
            $caseStatements = [];
            $bindings = [
                $escapedNormalized, // exact match
                $escapedNormalized.'%', // starts with
                '% '.$escapedNormalized.'%' // contains as whole word
            ];

            $termCases = [];
            foreach ($escapedTerms as $term) {
                $termCases[] = "(CASE
                            WHEN name LIKE ? THEN 100
                            WHEN meta_title LIKE ? THEN 80
                            WHEN meta_keywords LIKE ? THEN 60
                            WHEN meta_description LIKE ? THEN 40
                            ELSE 0
                        END)";
                array_push($bindings,
                    '%'.$term.'%', // name
                    '%'.$term.'%', // meta_title
                    '%'.$term.'%', // meta_keywords
                    '%'.$term.'%'  // meta_description
                );
            }

            $query->select([
                'products.*',
                \DB::raw("
                (CASE
                    WHEN name = ? THEN 1000
                    WHEN name LIKE ? THEN 900
                    WHEN name LIKE ? THEN 800
                    ELSE
                        (".implode(' + ', $termCases).")
                END) as search_rank
            ")
            ]);

            // Add all bindings
            $query->addBinding($bindings, 'select');

            // Add WHERE conditions with parameterized queries
            $query->where(function($q) use ($escapedTerms) {
                foreach ($escapedTerms as $term) {
                    $q->orWhere('name', 'LIKE', '%'.$term.'%')
                        ->orWhere('meta_title', 'LIKE', '%'.$term.'%')
                        ->orWhere('meta_keywords', 'LIKE', '%'.$term.'%')
                        ->orWhere('meta_description', 'LIKE', '%'.$term.'%')
                        ->orWhere('sku', 'LIKE', '%'.$term.'%')
                        ->orWhere('source_sku', 'LIKE', '%'.$term.'%');
                }
            });

            $query->orderByDesc('search_rank')
                ->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", [$escapedNormalized.'%'])
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
