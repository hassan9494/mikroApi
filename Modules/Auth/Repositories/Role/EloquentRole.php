<?php

namespace Modules\Auth\Repositories\Role;

use App\Repositories\Base\EloquentRepository;
use Spatie\Permission\Models\Role;

/**
 * Class EloquentRole
 * @package Modules\Auth\Repositories\Role
 */
class EloquentRole extends EloquentRepository implements RoleRepository
{

    /**
     * EloquentRole constructor.
     * @param Role $model
     */
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

}
