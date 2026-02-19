<?php

namespace App\Repositories\Base;

use App\Traits\Datatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class EloquentRepository
 * @package App\Repositories\Base
 */
class EloquentRepository implements BaseRepository
{
    /**
     * @var Model
     */
    protected  $model;
    /**
     * PLUCK API REQUEST QUERY SEARCH FIELD NAME
     * @var string
     */
    protected $querySearch = '';

    /**
     * EloquentRepository constructor.
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @param $id
     * @param array $with
     * @return Builder|Builder[]|Collection|Model
     */
    public function findOrFail($id, $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    /**
     * @param array $with
     * @return Builder|Builder[]|Collection|Model
     */
    public function firstOrFail($where = [], $with = [])
    {
        return $this->model->where($where)->with($with)->firstOrFail();
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        return $this->model->create($data);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $model = $this->findOrFail($id);
        $model->update($data);
        return $model;
    }

    /**
     * @inheritdoc
     */
    public function pluck($name = 'name', $key = 'id', $wheres = [])
    {
        return $this->model->where($wheres)->pluck($name, $key);
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
    public function get($wheres = [], $with = [])
    {
        return $this->model->with($with)->where($wheres)->get();
    }

    /**
     * @inheritdoc
     */
    public function latest($wheres = [], $with = [])
    {
        return $this->model->latest()->with($with)->where($wheres)->get();
    }

    /**
     * @param int $count
     * @param array $wheres
     * @param array $with
     * @param array $withCount
     * @return mixed
     */
    public function paginate($count = 20, $wheres = [], $with = [], $withCount = [])
    {
        return $this->model
            ->where($wheres)
            ->with($with)
            ->withCount($withCount)
            ->paginate($count);
    }


    /**
     * @param string $function
     * @return array|mixed
     */
    public function cached($function = 'All')
    {
        if (method_exists($this->model, 'cached'))
            return $this->model::cached($function);
        return [];
    }


    /**
     * @param array $searchColumns
     * @param array $with
     * @param array $whereHas
     * @return array[]
     */
    public function datatable($searchColumns = [], $with = [], $whereHas = [])
    {
        return Datatable::make($this->model)->search(...$searchColumns)->whereHas($whereHas)->with($with)->get();
    }

    /**
     * @return Model
     */
    public function model()
    {
        return $this->model;
    }

    public function all()
    {
        return $this->model->all();
    }


}
