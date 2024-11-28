<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Common\Repositories\Links\LinksRepositoryInterface;

class LinksController extends ApiAdminController
{

    /**
     * PromotionRepositoryInterface constructor.
     * @param LinksRepositoryInterface $repository
     */
    public function __construct(LinksRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'location' => $model->location,
            'email' => $model->email,
            'facebook' => $model->facebook,
            'instagram' => $model->instagram,
            'telegram' => $model->telegram,
            'whatsapp' => $model->whatsapp,
            'youtube' => $model->youtube,
            'call' => $model->call,
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


    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
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
            'location' => 'required',
            'email' => 'required',
            'facebook' => 'required',
            'instagram' => 'required',
            'telegram' => 'required',
            'whatsapp' => 'required',
            'youtube' => 'required',
            'call' => 'required',
        ]);
    }

}
