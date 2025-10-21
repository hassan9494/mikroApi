<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\DatatableFileResource;
use Modules\Common\Repositories\File\FileRepositoryInterface;

class FileController extends ApiAdminController
{

    /**
     * FileRepositoryInterface constructor.
     * @param FileRepositoryInterface $repository
     */
    public function __construct(FileRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'name')
            ->resource(DatatableFileResource::class)
            ->json();
    }

    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        $model->syncOneFile();

        // Return the file with link included
        return $this->success(
            new DatatableFileResource($model) // Use the same resource that includes the link
        );
    }

    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        $model->syncOneFile();

        // Return the file with link included
        return $this->success(
            new DatatableFileResource($model) // Use the same resource that includes the link
        );
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'file' => 'required|file',
        ]);
    }
}
