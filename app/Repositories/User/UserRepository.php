<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\Base\EloquentRepository;
use App\Traits\Datatable;

/**
 * Class EloquentRepository
 * @package App\Repositories\Base
 */
class UserRepository extends EloquentRepository implements UserRepositoryInterface
{

    /**
     * UserRepository constructor.
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * @param $email
     * @param array $with
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed
     */
    public function findOrFailByEmail($email, $with = [])
    {
        return $this->model
            ->with($with)
            ->where('email', $email)
            ->firstOrFail();
    }

    /**
     * @param $id
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function update($id, $data)
    {
        $model = parent::update($id, $data);

        if ($data['roles'] ?? false) {
            $model->syncRoles($data['roles']);
        }

        return $model;
    }


    /**
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function create( $data)
    {
        $model = parent::create( $data);

        if ($data['roles'] ?? false) {
            $model->syncRoles($data['roles']);
        }

        return $model;
    }

    /**
     * @param $email
     * @param array $with
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed|object|null
     */
    public function firstByEmail($email, $with = [])
    {
        return $this->model->with($with)->where('email', $email)->first();
    }

    /**
     * @inheritdoc
     */
    public function autocomplete($q, $limit = 20)
    {
        $query = $this->model
            ->where('name', 'LIKE', '%'.$q.'%')
            ->orWhere('email', 'LIKE', '%'.$q.'%')
            ->limit($limit);

//        if ($role) $query->role($role);

        return $query->get();
    }

    public function datatable($searchColumns = [], $with = [], $whereHas = [])
    {
        $roles = ['super'];
//        $x =  $this->mode;
        return Datatable::make($this->model)->search(...$searchColumns)->whereDosentHave($whereHas)->with($with)->get();
    }

}
