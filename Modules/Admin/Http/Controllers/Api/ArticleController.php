<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Blog\Repositories\Article\ArticleRepositoryInterface;

class ArticleController extends ApiAdminController
{

    /**
     * ArticleRepositoryInterface constructor.
     * @param ArticleRepositoryInterface $repository
     */
    public function __construct(ArticleRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'type' => $model->type,
            'title' => $model->title,
            'content' => $model->content,
            'video_url' => $model->video_url,
            'order' => $model->order,
            'media' => MediaResource::collection($model->media),
        ]);
    }

    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success($model);
    }


    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success($model);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'title' => 'required|max:255',
            'order' => 'nullable',
            'video_url' => 'nullable',
            'content' => 'nullable',
            'media' => 'nullable|array',
            'type' => 'required',
        ]);
    }

}
