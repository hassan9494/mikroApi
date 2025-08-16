<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;
use function PHPUnit\Framework\isFalse;

class Datatable
{

    /**
     * @var Model $model
     */
    private Model $model;

    private $query;

    private $resource;

    private $additionalData = [];

    private array $request = [];

    public static function make($model): Datatable
    {
        $datatable = new self();

        $datatable->model = $model;

        $page = request('page');
        $datatable->request['page'] = isset($page) ? request('page') +1 : 1;
        $datatable->request['limit'] = request('limit', 10);
        $datatable->request['order'] = json_decode(request('order', ''), true) ?? false;
        $datatable->request['conditions'] = json_decode(request('conditions', ''), true) ?? [];
        $datatable->request['search'] = request('search', '');

        $datatable->where();

        return $datatable;
    }

    private function where()
    {
//        dd($this->request);
        $where = [];
        $orWhere = [];
        foreach ($this->request['conditions'] as $key => $value) {

            if (is_array($value)) {
                if (isset($value['col'])) {
                    if(str_contains($value['col'], '|')) {
                        $column = explode('|', $value['col']);
                        $where[] = [$column[0], $value['op'] ?? '=', $value['val']];
                    } else {
                        // Handle "in" operator
                        if (isset($value['op']) && strtolower($value['op']) === 'in') {
                            $where[] = [$value['col'], $value['op'], $value['val']];
                        } else {
                            $where[] = [$value['col'], $value['op'] ?? '=', $value['val']];
                        }
                    }
                }
            }
            elseif ($value === "need"){
                $where[0] = ['stock','<',DB::raw('min_qty')];
                $where[1] = ['min_qty','>',0];
                $where[2] = [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
            }elseif ($value === "stock"){
                $where = [];
                $where[]= [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
            }elseif ($value === "nwaqes"){
                $where = [];
                $where[0] = ['stock','<',DB::raw('min_qty')];
                $where[1] = ['min_qty','>',0];
                $where[2]= [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
                $where[3] = ['is_retired',0];
            }elseif ($value === "sales"){
                $where = [];
            }
            else {
                $where[] = [$key, '=', $value];
            }
        }
        if (isset($this->request['needConditionReport'])){
            $where[0] = ['stock','<',DB::raw('min_qty')];
            $where[1] = ['min_qty','>',0];
        }
//        dd($where);
        $this->query = $this->model->where($where)->orWhere($orWhere);
//        dd($where);
        $this->query = $this->model->where(function($query) use ($where) {
            foreach ($where as $condition) {
                if (is_callable($condition)) {
                    $condition($query);
                } elseif (count($condition) === 3 && $condition[1] === 'in') {
                    $query->whereIn($condition[0], $condition[2]);
                } else {
                    $query->where([$condition]);
                }
            }
        })->orWhere($orWhere);
    }

    private function order()
    {
        if (!$this->request['order'])
            return;

        $this->query->orderBy(
            $this->request['order']['column'],
            $this->request['order']['dir']
        );
    }

    /**
     * @param array $whereHas
     * @return $this
     */
    public function whereHas(array $whereHas = []): Datatable
    {
        foreach ($whereHas as $key => $callback)
            $this->query->whereHas($key, $callback);
        return $this;
    }

    /**
     * @param ...$columns
     * @return $this
     */
    public function search(...$columns): Datatable
    {
        $search = strtolower(request('search', ''));
        if ($search) {
            $this->query->where(function($query) use ($columns, $search){
                foreach ($columns as $column) {
                    // Handle JSON columns (e.g., customer->name)
                    if (str_contains($column, '->')) {
                        [$jsonColumn, $jsonKey] = explode('->', $column);
                        $query->orWhereRaw(
                            "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`{$jsonColumn}`, '$.\"{$jsonKey}\"'))) LIKE ?",
                            ['%' . $search . '%']
                        );
                    } else {
                        // Handle non-JSON columns (e.g., id, total)
                        $query->orWhereRaw("LOWER(`{$column}`) LIKE ?", ['%' . $search . '%']);
                    }
                }
            });
        }
        return $this;
    }



    /**
     * Enhanced custom search function for Datatable
     *
     * @param string ...$columns
     * @return $this
     */
    public function custom_search(...$columns): self
    {
        $search = request('search', '');
        if ($search) {
            $searchTerms = explode(' ', strtolower($search));
            $termCount = count($searchTerms);

            // Build the query for name and meta search
            $this->query->where(function ($query) use ($columns, $searchTerms) {
                foreach ($columns as $column) {
                    foreach ($searchTerms as $term) {
                        if ($column === 'meta') {
                            // Search within JSON fields in meta
                            $query->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.title'))) LIKE ?", ['%' . $term . '%'])
                                ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.keywords'))) LIKE ?", ['%' . $term . '%'])
                                ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.description'))) LIKE ?", ['%' . $term . '%']);
                        } else {
                            // Search within regular columns
                            $query->orWhere($column, 'LIKE', '%' . $term . '%');
                        }
                    }
                }
            });

            // Create ranking logic similar to autocomplete
            $rankingQuery = "CASE
                        WHEN LOWER(name) = '" . strtolower($search) . "' THEN " . ($termCount + 1) . "
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

            // Add ranking to the query
            $this->query->addSelect(['*', \DB::raw($rankingQuery)]);

            // Order by rank and other criteria
            $this->query->orderByDesc('search_rank')
                ->orderByRaw("CASE WHEN LOWER(name) LIKE '" . strtolower($search) . "%' THEN 0 ELSE 1 END")
                ->orderBy('name');
        }

        return $this;
    }




    /**
     * @param array $with
     * @return $this
     */
    public function with(array $with = []): Datatable
    {
        $this->query->with($with);
        return $this;
    }

    /**
     * @param array $whereDoesntHave
     * @return $this
     */
    public function whereDosentHave(array $whereDoesntHave = []): Datatable
    {
        $this->query->whereDoesntHave('roles', function ($query) use ($whereDoesntHave) {
            $query->whereIn('name', $whereDoesntHave);
        });
        return $this;
    }

    /**
     * @param array $whereDoesntHave
     * @return $this
     */
    public function whereHave(array $whereDoesntHave = []): Datatable
    {
        if (count($whereDoesntHave) > 0) {
            $this->query->whereHas('roles', function ($query) use ($whereDoesntHave) {
                $query->whereIn('name', $whereDoesntHave);
            });
            return $this;
        }
        return $this;
    }


    /**
     * @param array $with
     * @return $this
     */
    public function withCount(array $with = []): Datatable
    {
        $this->query->withCount($with);
        return $this;
    }

    /**
     * @param $resource
     * @return $this
     */
    public function resource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @param $additionalData
     * @return $this
     */
    public function additionalData(array $data): Datatable
    {
        $this->additionalData = $data;
        return $this;
    }

    /**
     * @return array[]
     */
    #[ArrayShape(['items' => "mixed", 'total' => "mixed"])]
    public function get(): array
    {
        $total = $this->query->count();

        $this->query
            ->limit($this->request['limit'])
            ->offset($this->request['limit'] * ($this->request['page'] - 1)); // Adjusted offset calculation

        $this->order();

        $items = $this->query->get();

        // Pass additional data to the resource
        if ($this->resource) {
            $items = $this->resource::collection($items)->additional($this->additionalData);
        }

        return [
            'items' => $items,
            'total' => $total
        ];
    }




    /**
     * @return JsonResponse
     */
    public function json(): JsonResponse
    {
        return response()->json([
            'data' => $this->get()
        ]);
    }

}
