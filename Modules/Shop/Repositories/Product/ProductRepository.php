<?php

namespace Modules\Shop\Repositories\Product;

use App\Repositories\Base\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Shop\Entities\Product;
use Modules\Shop\Support\Enums\InvoiceStatus;
use Illuminate\Support\Facades\DB;

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
        // Preserve newlines in short description
        if (isset($data['short_description'])) {
            $data['short_description'] = str_replace("\n", '<br>', $data['short_description']);
        }
        if (isset($data['short_description_ar'])) {
            $data['short_description_ar'] = str_replace("\n", '<br>', $data['short_description_ar']);
        }

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
        if (isset($data['short_description'])) {
            $data['short_description'] = str_replace("\n", '<br>', $data['short_description']);
        }
        if (isset($data['short_description_ar'])) {
            $data['short_description_ar'] = str_replace("\n", '<br>', $data['short_description_ar']);
        }

        if (!empty($data['stock'])){
            $data['stock'] = (int)$data['stock'];
        }


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
        Log::info("Elasticsearch searchWord", [
            'searchWord' => $searchWord,
            'category' => $category,
            'filter' => $filter,
        ]);
        $client = app('elasticsearch');
        $page = request()->get('page', 1);
        $from = ($page - 1) * $limit;
        $searchWord = trim($searchWord);

        if (empty($searchWord)) {
            return $this->getAllProductsQuery($client, $category, $limit, $filter, $inStock, $page);
        }

        $cleanQuery = preg_replace('/\s+/', ' ', $searchWord);
        $words = array_filter(explode(' ', $cleanQuery));

        if (empty($words) || strlen($cleanQuery) < 2) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $limit);
        }

        // Build the exact same search query from your standalone PHP file
        $shouldClauses = [];

        // 1. Exact phrase match (highest priority)
        $shouldClauses[] = [
            'multi_match' => [
                'query' => $searchWord,
                'type' => 'phrase',
                'fields' => ['name^10', 'sku^10', 'source_sku^8'],
                'boost' => 1000
            ]
        ];

        // 2. SKU exact matches
        foreach (['sku', 'source_sku'] as $field) {
            $shouldClauses[] = [
                'term' => [
                    "{$field}.keyword" => [
                        'value' => $searchWord,
                        'boost' => 800
                    ]
                ]
            ];
        }

        // 3. Prefix matches for SKUs
        foreach (['sku', 'source_sku'] as $field) {
            $shouldClauses[] = [
                'prefix' => [
                    "{$field}.keyword" => [
                        'value' => strtolower($searchWord),
                        'boost' => 600
                    ]
                ]
            ];
        }

        // 4. Cross-fields search
        $shouldClauses[] = [
            'multi_match' => [
                'query' => $cleanQuery,
                'type' => 'cross_fields',
                'operator' => 'and',
                'fields' => ['name^5', 'sku^5', 'source_sku^4', 'meta_title^3', 'short_description^1'],
                'boost' => 400
            ]
        ];

        // 5. Best fields match
        $shouldClauses[] = [
            'multi_match' => [
                'query' => $cleanQuery,
                'type' => 'best_fields',
                'fields' => ['name^5', 'sku^5', 'source_sku^4', 'meta_title^2'],
                'boost' => 200
            ]
        ];

        // 6. Partial matches
        if (count($words) > 1) {
            $shouldClauses[] = [
                'multi_match' => [
                    'query' => $cleanQuery,
                    'minimum_should_match' => '60%',
                    'fields' => ['name^3', 'short_description^1'],
                    'boost' => 100
                ]
            ];
        }

        $body = [
            'from' => $from,
            'size' => $limit,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                    'filter' => []
                ]
            ],
            'sort' => []
        ];

        // Add filters
        if ($category && $category !== 'new_product') {
            $body['query']['bool']['filter'][] = [
                'term' => ['category_slugs' => $category]
            ];
        }

        if ($inStock) {
            $body['query']['bool']['filter'][] = [
                'range' => ['stock' => ['gt' => 0]]
            ];
        }

        // Boost available, non-retired products
        $body['query']['bool']['should'][] = [
            'bool' => [
                'must' => [
                    ['term' => ['available' => true]],
                    ['term' => ['is_retired' => false]]
                ],
                'boost' => 50
            ]
        ];

        // Apply sorting
        switch ($filter) {
            case 'new-item':
                $body['sort'][] = ['created_at' => ['order' => 'desc']];
                break;
            case 'old-item':
                $body['sort'][] = ['created_at' => ['order' => 'asc']];
                break;
            case 'price-high':
                $body['sort'][] = ['effective_price' => ['order' => 'desc']];
                break;
            case 'price-low':
                $body['sort'][] = ['effective_price' => ['order' => 'asc']];
                break;
            case 'sale':
                $body['query']['bool']['filter'][] = [
                    'range' => ['sale_price' => ['gt' => 0]]
                ];
                $body['sort'][] = ['_score' => ['order' => 'desc']];
                break;
            default:
                $body['sort'][] = ['_score' => ['order' => 'desc']];
                break;
        }

        return $this->executeSearch($client, $body, $limit, $page);
    }

    /**
     * Get all products query (when search is empty)
     */
    private function getAllProductsQuery($client, $category, $limit, $filter, $inStock, $page)
    {
        $from = ($page - 1) * $limit;

        $body = [
            'from' => $from,
            'size' => $limit,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'must' => ['match_all' => (object)[]],
                    'filter' => []
                ]
            ],
            'sort' => []
        ];

        // Add filters
        if ($category && $category !== 'new_product') {
            $body['query']['bool']['filter'][] = [
                'term' => ['category_slugs' => $category]
            ];
        }

        if ($inStock) {
            $body['query']['bool']['filter'][] = [
                'range' => ['stock' => ['gt' => 0]]
            ];
        }

        // Apply sorting
        switch ($filter) {
            case 'new-item':
                $body['sort'][] = ['created_at' => ['order' => 'desc']];
                break;
            case 'old-item':
                $body['sort'][] = ['created_at' => ['order' => 'asc']];
                break;
            case 'price-high':
                $body['sort'][] = ['effective_price' => ['order' => 'desc']];
                break;
            case 'price-low':
                $body['sort'][] = ['effective_price' => ['order' => 'asc']];
                break;
            case 'sale':
                $body['query']['bool']['filter'][] = [
                    'range' => ['sale_price' => ['gt' => 0]]
                ];
                $body['sort'][] = ['created_at' => ['order' => 'desc']];
                break;
            default:
                $body['sort'][] = ['created_at' => ['order' => 'desc']];
                break;
        }

        return $this->executeSearch($client, $body, $limit, $page);
    }

    private function executeSearch($client, $body, $limit, $page)
    {

        try {
            $response = $client->search([
                'index' => env('ELASTICSEARCH_INDEX', 'test_productssss'),
                'body' => $body
            ]);

            $total = $response['hits']['total']['value'];
            Log::info("Total", [
                'total' => $total,
                'response' => $response['hits']['hits'],
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator(
                $response['hits']['hits'],
                $total,
                $limit,
                $page
            );
        } catch (\Exception $e) {
//            dd('test');
            // Fallback to old search
            return $this->old_search(request()->get('search', ''), request()->get('category', ''), $limit, request()->get('filter', ''), request()->get('inStock', false));
        }
    }


    public function search1elastic($searchWord, $category, $limit = 20, $filter, $inStock = false)
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

            return $this->executeSearch($client, $body, $limit, $page, $searchWord, $category, $filter, $inStock);
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
//        $sort[] = ['stock' => 'asc'];
        $body['sort'] = $sort;

        return $this->executeSearch($client, $body, $limit, $page, $searchWord, $category, $filter, $inStock);
    }




    public function old_search($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
//dd('test');
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
                        ->orWhere('location', 'LIKE', '%'.$term.'%')
                        ->orWhere('stock_location', 'LIKE', '%'.$term.'%')
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
            CAST(
                CASE
                    WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END
            AS DECIMAL(10,2)) DESC
        ');
                break;
            case "price-low":
                $query->orderByRaw('
            CAST(
                CASE
                    WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END
            AS DECIMAL(10,2)) ASC
        ');
                break;
            default:
                // No filter applied
                break;
        }


        // Check stock availability based on the parameter
        if ($inStock === true || $inStock === "true") {
            $query->where('stock', '>', 0);
        } else {
            $query->where('stock', '>=', 0);
        }

        return $query->paginate($limit);
    }

    public function old_search2($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
        // Generate a unique cache key based on all search parameters
        $cacheKey = 'product_search_' . md5(serialize([
                'search' => $searchWord,
                'category' => $category,
                'limit' => $limit,
                'filter' => $filter,
                'inStock' => $inStock,
                'page' => request()->get('page', 1)
            ]));

        // Check if results are cached
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $query = Product::query();
        $searchWord = trim($searchWord);

        if ($searchWord) {
            // Normalize search query according to general notes
            $normalizedQuery = $this->normalizeSearchQuery($searchWord);

//            dd($normalizedQuery);
            $searchTerms = array_filter(explode(' ', $normalizedQuery));

            // Minimum 2 characters required (general note #1)
            if (strlen($normalizedQuery) < 2) {
                return collect([]);
            }

            // Build the ranking system
            $termCountExpression = $this->buildTermCountExpression($searchTerms);
            $excelPriorityCases = $this->buildExcelPriorityCases($searchTerms, $normalizedQuery);

            // Get all bindings
            $bindings = $this->getSearchBindings($searchTerms, $normalizedQuery);

            $query->selectRaw(
                "products.*, ($termCountExpression) * 1000000 +
            CASE $excelPriorityCases ELSE 0 END as search_rank",
                $bindings
            );

            // Add WHERE conditions - optimized for indexed fields
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    if (strlen($term) >= 2) {
                        $q->orWhere(function($innerQ) use ($term) {
                            $innerQ->where('name', 'LIKE', "%$term%")
                                ->orWhere('meta_title', 'LIKE', "%$term%")
                                ->orWhere('meta_keywords', 'LIKE', "%$term%")
                                ->orWhere('meta_description', 'LIKE', "%$term%")
                                ->orWhere('sku', 'LIKE', "%$term%")
                                ->orWhere('location', $term)
                                ->orWhere('stock_location',$term)
                                ->orWhere('source_sku', 'LIKE', "%$term%");
                        });
                    }
                }
            });

            $query->orderByDesc('search_rank');
        }
        elseif ($category) {
            // Search by category if no search word is provided
            if ($category == 'new_product'){
                $latestIds = Product::query()
                    ->orderBy('id', 'desc')
                    ->take(720)
                    ->pluck('id');

                $query->whereIn('id', $latestIds)
                    ->orderBy('id', 'desc');
            } else {
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
            CAST(
                CASE
                    WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END
            AS DECIMAL(10,2)) DESC
        ');
                break;
            case "price-low":
                $query->orderByRaw('
            CAST(
                CASE
                    WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                    THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                    ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                END
            AS DECIMAL(10,2)) ASC
        ');
                break;
            default:
                if ($searchWord) {
                    $query->orderByDesc('search_rank');
                }
                break;
        }

        // Check stock availability based on the parameter
        if ($inStock === true || $inStock === "true") {
            $query->where('stock', '>', 0);
        } else {
            $query->where('stock', '>=', 0);
        }

        // Execute the query and cache the results
        $results = $query->paginate($limit);

        // Cache for 5 minutes (adjust based on your needs)
        Cache::put($cacheKey, $results, 300);

        return $results;
    }

    /**
     * Build expression to count matching terms with exact word matching using LIKE
     */
    private function buildTermCountExpression($searchTerms)
    {
        $expressions = [];

        $fields = ['name', 'meta_title', 'meta_keywords', 'meta_description',
            'sku', 'source_sku', 'location', 'stock_location', 'short_description'];

        foreach ($searchTerms as $term) {
            $fieldConditions = [];
            foreach ($fields as $field) {
                // Simulate word boundaries using multiple LIKE conditions
                $fieldConditions[] = "($field = ? OR $field LIKE ? OR $field LIKE ? OR $field LIKE ?)";
            }
            $expressions[] = "IF(" . implode(" OR ", $fieldConditions) . ", 1, 0)";
        }

        return implode(" + ", $expressions);
    }

    /**
     * Build Excel priority cases with parameter binding and exact word matching using LIKE
     */
    private function buildExcelPriorityCases($searchTerms, $normalizedQuery)
    {
        $cases = [];

        // Priority 1: Exact phrase match with case sensitivity
        $cases[] = "WHEN name = ? THEN 10000";

        // Priority 2: Exact phrase match ignoring case
        $cases[] = "WHEN LOWER(name) = LOWER(?) THEN 9000";

        // Priority 3: All words in exact order (adjacent)
        $adjacentPattern = implode(' ', $searchTerms);
        $cases[] = "WHEN name LIKE ? THEN 8500";

        // Priority 4: All words in exact order (non-adjacent)
        $nonAdjacentPattern = '%' . implode('%', $searchTerms) . '%';
        $cases[] = "WHEN name LIKE ? THEN 8000";

        // Priority 5: All words in any order
        if (count($searchTerms) > 1) {
            $allWordsConditions = [];
            foreach ($searchTerms as $term) {
                $allWordsConditions[] = "(name = ? OR name LIKE ? OR name LIKE ? OR name LIKE ?)";
            }
            $cases[] = "WHEN " . implode(" AND ", $allWordsConditions) . " THEN 7500";
        }

        // Priority 6: Single exact word match
        $cases[] = "WHEN (name = ? OR name LIKE ? OR name LIKE ? OR name LIKE ?) THEN 5000";

        // Priority 7: Partial word match (only if term is at least 3 characters)
        if (count($searchTerms) > 0 && strlen($searchTerms[0]) >= 3) {
            $cases[] = "WHEN name LIKE ? THEN 4000";
        }

        // Other fields with exact matching
        $otherFields = [
            'meta_title' => 3000,
            'meta_keywords' => 2500,
            'meta_description' => 2000,
            'sku' => 1500,
            'source_sku' => 1500,
            'location' => 1000,
            'stock_location' => 1000,
            'short_description' => 500
        ];

        foreach ($otherFields as $field => $weight) {
            $cases[] = "WHEN ($field = ? OR $field LIKE ? OR $field LIKE ? OR $field LIKE ?) THEN $weight";
            $cases[] = "WHEN $field LIKE ? THEN " . ($weight - 250); // Lower priority for partial matches
        }

        return implode(" ", $cases);
    }

    /**
     * Get all search bindings in the correct order for LIKE-based word boundaries
     */
    private function getSearchBindings($searchTerms, $normalizedQuery)
    {
        $bindings = [];

        // Bindings for term count expression
        foreach ($searchTerms as $term) {
            $fields = ['name', 'meta_title', 'meta_keywords', 'meta_description',
                'sku', 'source_sku', 'location', 'stock_location', 'short_description'];

            foreach ($fields as $field) {
                $bindings[] = $term; // For field = term
                $bindings[] = "$term %"; // For term at start
                $bindings[] = "% $term"; // For term at end
                $bindings[] = "% $term %"; // For term in middle
            }
        }

        // Bindings for Excel priority cases

        // Priority 1 and 2 bindings
        $bindings[] = $normalizedQuery;
        $bindings[] = $normalizedQuery;

        // Priority 3: Adjacent words
        $adjacentPattern = '%' . implode(' ', $searchTerms) . '%';
        $bindings[] = $adjacentPattern;

        // Priority 4: Non-adjacent but in order
        $nonAdjacentPattern = '%' . implode('%', $searchTerms) . '%';
        $bindings[] = $nonAdjacentPattern;

        // Priority 5 bindings (only for multiple terms)
        if (count($searchTerms) > 1) {
            foreach ($searchTerms as $term) {
                $bindings[] = $term;
                $bindings[] = "$term %";
                $bindings[] = "% $term";
                $bindings[] = "% $term %";
            }
        }

        // Priority 6 binding
        $bindings[] = $searchTerms[0];
        $bindings[] = "{$searchTerms[0]} %";
        $bindings[] = "% {$searchTerms[0]}";
        $bindings[] = "% {$searchTerms[0]} %";

        // Priority 7 binding (only if term is at least 3 characters)
        if (count($searchTerms) > 0 && strlen($searchTerms[0]) >= 3) {
            $bindings[] = "%" . substr($searchTerms[0], 0, 3) . "%";
        }

        // Other fields bindings for exact matches
        $otherFields = ['meta_title', 'meta_keywords', 'meta_description',
            'sku', 'source_sku', 'location', 'stock_location', 'short_description'];

        foreach ($otherFields as $field) {
            $bindings[] = $normalizedQuery;
            $bindings[] = "$normalizedQuery %";
            $bindings[] = "% $normalizedQuery";
            $bindings[] = "% $normalizedQuery %";
            $bindings[] = "%$normalizedQuery%";
        }

        return $bindings;
    }

    /**
     * Normalize search query according to Excel requirements
     */
    private function normalizeSearchQuery($query)
    {
        // Only convert to lowercase and trim
        $query = strtolower(trim($query));

        // Remove multiple spaces (but preserve all special characters)
        $query = preg_replace('/\s+/', ' ', $query);

        return $query;
    }


    public function old_search3($searchWord, $category, $limit = 20, $filter, $inStock = false)
    {
        $query = Product::query();
        $searchWord = trim($searchWord);

        // Handle search by name and meta
        if ($searchWord) {
            // Normalize the search query
            $normalized = trim(preg_replace('/\s+/', ' ', $searchWord));
            $searchTerms = array_filter(explode(' ', $normalized));
            $termCount = count($searchTerms);

            if ($termCount === 0) {
                return $query->paginate($limit);
            }

            // Build the complex ranking system
            $rankingCases = [];
            $bindings = [];

            // 1. Exact phrase match with highest priority (boost: 1000)
            $exactFields = [
                'name' => 5, 'sku' => 5, 'source_sku' => 5,
                'location' => 3, 'stock_location' => 3,
                'meta_title' => 2, 'meta_keywords' => 2, 'meta_description' => 1
            ];

            foreach ($exactFields as $field => $boost) {
                $rankingCases[] = "WHEN $field = ? THEN " . (1000 * $boost);
                $bindings[] = $normalized;
            }

            // 2. Phrase match (boost: 800)
            foreach ($exactFields as $field => $boost) {
                $rankingCases[] = "WHEN $field LIKE ? THEN " . (800 * $boost);
                $bindings[] = "%{$normalized}%";
            }

            // 3. Wildcard matches on key fields (boost: 600)
            $wildcardFields = ['name', 'sku', 'source_sku'];
            foreach ($wildcardFields as $field) {
                $rankingCases[] = "WHEN $field LIKE ? THEN 600";
                $bindings[] = "%{$normalized}%";
            }

            // 4. Individual exact word matches (boost: 500)
            foreach ($searchTerms as $term) {
                if (strlen($term) >= 2) {
                    $exactWordFields = ['name', 'sku', 'source_sku'];
                    foreach ($exactWordFields as $field) {
                        $rankingCases[] = "WHEN $field = ? THEN 500";
                        $bindings[] = $term;
                    }
                }
            }

            // 5. All words in any order (boost: 400)
            $allWordsConditions = [];
            foreach ($searchTerms as $term) {
                $fieldConditions = [];
                foreach ($exactFields as $field => $boost) {
                    $fieldConditions[] = "$field LIKE ?";
                    $bindings[] = "%{$term}%";
                }
                $allWordsConditions[] = "(" . implode(" OR ", $fieldConditions) . ")";
            }

            $rankingCases[] = "WHEN " . implode(" AND ", $allWordsConditions) . " THEN 400";

            // 6. Bigrams for multi-word searches (boost: 300)
            if ($termCount > 1) {
                for ($i = 0; $i < $termCount - 1; $i++) {
                    $bigram = $searchTerms[$i] . ' ' . $searchTerms[$i + 1];
                    $rankingCases[] = "WHEN name LIKE ? THEN 300";
                    $bindings[] = "%{$bigram}%";
                }
            }

            // 7. At least half words match (boost: 200)
            $minShouldMatch = max(1, floor($termCount / 2));
            $halfMatchConditions = [];
            foreach ($searchTerms as $term) {
                $fieldConditions = [];
                foreach ($exactFields as $field => $boost) {
                    $fieldConditions[] = "$field LIKE ?";
                    $bindings[] = "%{$term}%";
                }
                $halfMatchConditions[] = "(" . implode(" OR ", $fieldConditions) . ")";
            }

            $rankingCases[] = "WHEN (" . implode(" + ", array_map(function($cond) {
                    return "CASE WHEN $cond THEN 1 ELSE 0 END";
                }, $halfMatchConditions)) . ") >= $minShouldMatch THEN 200";

            // 8. Single word matches (boost: 100)
            foreach ($searchTerms as $term) {
                foreach ($exactFields as $field => $boost) {
                    $rankingCases[] = "WHEN $field LIKE ? THEN " . (100 * $boost);
                    $bindings[] = "%{$term}%";
                }
            }

            // 9. Single word wildcards (boost: 25-50 based on length)
            foreach ($searchTerms as $term) {
                if (strlen($term) >= 2) {
                    $boost = strlen($term) < 3 ? 25 : 50;
                    $wildcardFields = ['name', 'sku', 'source_sku'];
                    foreach ($wildcardFields as $field) {
                        $rankingCases[] = "WHEN $field LIKE ? THEN $boost";
                        $bindings[] = "%{$term}%";
                    }
                }
            }

            // 10. Short description fallback (boost: 1)
            $rankingCases[] = "WHEN short_description LIKE ? THEN 1";
            $bindings[] = "%{$normalized}%";

            foreach ($searchTerms as $term) {
                $rankingCases[] = "WHEN short_description LIKE ? THEN 1";
                $bindings[] = "%{$term}%";
            }

            // Build the final CASE statement
            $caseStatement = "CASE\n" . implode("\n", $rankingCases) . "\nELSE 0 END";

            $query->select([
                'products.*',
                \DB::raw("$caseStatement as search_rank")
            ]);

            // Add all bindings
            foreach ($bindings as $binding) {
                $query->addBinding($binding, 'select');
            }

            // Add WHERE conditions
            $query->where(function($q) use ($searchTerms, $exactFields) {
                foreach ($searchTerms as $term) {
                    $q->orWhere(function($innerQ) use ($term, $exactFields) {
                        foreach (array_keys($exactFields) as $field) {
                            $innerQ->orWhere($field, 'LIKE', "%{$term}%");
                        }
                        $innerQ->orWhere('short_description', 'LIKE', "%{$term}%");
                    });
                }
            });

            $query->orderByDesc('search_rank');
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
            } else {
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
                CAST(
                    CASE
                        WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                        THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                    END
                AS DECIMAL(10,2)) DESC
            ');
                break;
            case "price-low":
                $query->orderByRaw('
                CAST(
                    CASE
                        WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price")) AS DECIMAL(10,2)) = 0
                        THEN JSON_UNQUOTE(JSON_EXTRACT(price, "$.normal_price"))
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(price, "$.sale_price"))
                    END
                AS DECIMAL(10,2)) ASC
            ');
                break;
            default:
                // No filter applied, but maintain search ranking if it exists
                if ($searchWord) {
                    $query->orderByDesc('search_rank');
                }
                break;
        }

        // Check stock availability based on the parameter
        if ($inStock === true || $inStock === "true") {
            $query->where('stock', '>', 0);
        } else {
            $query->where('stock', '>=', 0);
        }

        return $query->paginate($limit);
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
            'index' => env('ELASTICSEARCH_INDEX', 'test_productssss'),
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

        $inpStrArray = explode(" ", $searchWord);
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


    public function getBackinStockProducts($limit = 20)
    {
        $subquery = \DB::table('invoice_products')
            ->select('invoice_products.product_id', \DB::raw('MAX(COALESCE(invoices.completed_at, invoices.date)) as latest_date'))
            ->join('invoices', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'COMPLETED')
            ->groupBy('invoice_products.product_id');

        return $this->model
            ->select('products.*', 'latest_invoice.latest_date')
            ->joinSub($subquery, 'latest_invoice', function ($join) {
                $join->on('products.id', '=', 'latest_invoice.product_id');
            })
            ->orderBy('latest_invoice.latest_date', 'DESC')
            ->with(['categories', 'media'])
            ->paginate($limit);
    }
    public function getBackinStockProductsQuery()
    {
        $subquery = \DB::table('invoice_products')
            ->select('invoice_products.product_id', \DB::raw('MAX(COALESCE(invoices.completed_at, invoices.date)) as latest_date'))
            ->join('invoices', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'COMPLETED')
            ->groupBy('invoice_products.product_id');

        return $this->model
            ->select('products.*', 'latest_invoice.latest_date')
            ->joinSub($subquery, 'latest_invoice', function ($join) {
                $join->on('products.id', '=', 'latest_invoice.product_id');
            })
            ->with(['categories', 'media']);
    }




}
