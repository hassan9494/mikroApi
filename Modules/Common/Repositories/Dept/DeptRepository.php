<?php

namespace Modules\Common\Repositories\Dept;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Dept;

/**
 * Class DeptRepository
 * @package Modules\Shop\Repositories\Category
 */
class DeptRepository extends EloquentRepository implements DeptRepositoryInterface
{

    /**
     * DeptRepository constructor.
     * @param Dept $model
     */
    public function __construct(Dept $model)
    {
        parent::__construct($model);
    }

}
