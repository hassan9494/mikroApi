<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiAdminController
{

    /**
     * @param UserRepositoryInterface $repository
     */
    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $search = $this->datatableSearchFields();
        $user = Auth::user();

        if ($user->hasRole(['super'])){
            $whereDosntHas = [];
        }elseif($user->hasRole(['admin']))
        {
            $whereDosntHas =  ['super'];
        }else{
            $whereDosntHas =  ['super','admin','Admin cash'];
        }

        return $this->success(
            $this->repository->datatable($search, ['roles'],$whereDosntHas,[])
        );
    }

    /**
     * @return JsonResponse
     */
    public function employee(): JsonResponse
    {
        $search = $this->datatableSearchFields();
        $user = Auth::user();

        if ($user->hasRole(['super'])){
            $whereHas = ['super','admin','Admin cash','Distributer','Cashier','Product Manager','Manager'];
        }elseif($user->hasRole(['admin']))
        {
            $whereHas =  ['admin','Admin cash','Distributer','Cashier','Product Manager','Manager'];
        }else{
            $whereHas =  ['Admin cash','Distributer','Cashier','Product Manager','Manager'];
        }

        return $this->success(
            $this->repository->datatable($search, ['roles'],[],$whereHas)
        );
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'name', 'email', 'phone'];
    }


    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $data = $this->validate();
        request()->validate([
            'password' => 'required|min:8|max:32|confirmed',
            'password_confirmation' => 'required',
        ]);
        if (request('password') == request('password_confirmation') ){
            $data['password'] = Hash::make(request('password'));
            return $this->success(
                $this->repository->create($data)
            );
        }else{
            abort(500, 'The password does not match the password confirm');
        }

    }


    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        if ($id == 1) abort(403, 'Please ask the manager to update this user!');
        $data = $this->validate();
        return $this->success(
            $this->repository->update($id, $data)
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id, 'roles');
        return $this->success($model);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $id = request()->route()->parameter('user');
        return request()->validate([
            'name' => 'required|max:255',
            'email' => 'required|unique:users,email,'.$id,
            'phone' => 'required',
            'status' => 'required|numeric',
            'roles' => 'required',
            'roles.*' => 'required|exists:roles,name',
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function autocomplete(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocomplete($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
            ];
        }
        return $this->success($response);
    }

    /**
     * @return JsonResponse
     */
    public function autocompletecashier(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocompletecashier($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
            ];
        }
        return $this->success($response);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function verificationEmail($id): JsonResponse
    {
        $user = $this->repository->findOrFail($id);
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification($id);
        }
        return $this->success();
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword($id, Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|min:8|max:32|confirmed',
            'password_confirmation' => 'required',
        ]);
        $this->repository->update($id, [
            'password'=> Hash::make($request->password)
        ]);
        return $this->success();
    }

}
