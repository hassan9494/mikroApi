<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $where = [
            [
                'name', '!=', 'super'
            ],
            [
                'name', '!=', 'admin'
            ]
        ];
        if (!$user->hasRole('super') && !$user->hasRole('admin') && $user->hasRole('Manager') ) {
            array_push($where,['name','!=','Admin cash']);
            $data = $this->repository->get($where);
        }elseif (!$user->hasRole('super') ){
            $data = $this->repository->get($where);
        }
        else{
            $data = $this->repository->get();
        }

        return response()->json([
            'data' => $data
        ]);
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
