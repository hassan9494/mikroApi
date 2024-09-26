<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Common\Repositories\Tag\TagRepositoryInterface;

class TagController extends ApiAdminController
{
    /**
     * SlideRepositoryInterface constructor.
     * @param TagRepositoryInterface $repository
     */
    public function __construct(TagRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'script' => $model->script,
            'type' => $model->type,
        ]);
    }



    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        return $this->success(
            $model
        );
    }











    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'script' => 'required',
            'type' => 'required',
        ]);
    }
}
