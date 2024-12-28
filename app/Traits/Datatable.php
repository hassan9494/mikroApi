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

    private array $request = [];

    public static function make($model): Datatable
    {
        $datatable = new self();

        $datatable->model = $model;

        $datatable->request['page'] = request('page', 1);
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

                if (isset($value['col']))
                    if(str_contains($value['col'], '|')){
                        $column = explode('|',$value['col']) ;
                        $where[] = [$column[0], $value['op'] ?? '=', $value['val']];
                        $orWhere[] = [$column[1], $value['op']?? '=', $value['val']];
                    }else{
                        $where[] = [$value['col'], $value['op'] ?? '=', $value['val']];
                        $orWhere[] = [$value['col'], $value['op'] ?? '=', $value['val']];
                    }

            }elseif ($value === "need"){
                $where[0] = ['stock','<',DB::raw('min_qty')];
                $where[1] = ['min_qty','>',0];
//                $where[2] = [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
            }elseif ($value === "stock"){
                $where = [];
                $where[]= [DB::raw("JSON_UNQUOTE(JSON_EXTRACT(`options`, '$.kit'))"), '=', 'false'];
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
        $search = request('search', '');
        if ($search) {
            $this->query->where(function($query) use ($columns, $search){
                foreach ($columns as $column)
                    $query->orWhere($column, 'LIKE', '%'.$search.'%');
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

            $this->query->where(function ($query) use ($columns, $searchTerms) {
                foreach ($columns as $column) {
                    foreach ($searchTerms as $term) {
                        if ($column === 'meta') {
                            $query->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.title'))) LIKE ?", ['%' . $term . '%'])
                                ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.keywords'))) LIKE ?", ['%' . $term . '%'])
                                ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.description'))) LIKE ?", ['%' . $term . '%']);
                        } else {
                            $query->orWhere($column, 'LIKE', '%' . $term . '%');
                        }
                    }
                }
            });
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
     * @return array[]
     */
    #[ArrayShape(['items' => "mixed", 'total' => "mixed"])]
    public function get(): array
    {
        $total = $this->query->count();

        $this->query
            ->limit($this->request['limit'])
            ->offset($this->request['limit'] * $this->request['page']);

        $this->order();

        $items = $this->query->get();

        return [
            'items' => $this->resource ? $this->resource::collection($items) : $items,
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
