<?php

namespace Modules\Admin\Http\Controllers\Api;

use Modules\Auth\Repositories\Role\RoleRepository;

class RoleController extends ApiAdminController
{

    /**
     * @param RoleRepository $repository
     */
    public function __construct(RoleRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'guard' => 'nullable',
        ]);
    }

}
