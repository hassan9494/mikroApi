<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Common\Repositories\Course\CourseRepositoryInterface;

class CourseController extends ApiAdminController
{

    /**
     * CourseController constructor.
     * @param CourseRepositoryInterface $repository
     */
    public function __construct(CourseRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search($this->datatableSearchFields())
            ->withCount(['students'])
            ->json();
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function payments($id): JsonResponse
    {
        $model = $this->repository->findOrFail($id, ['payments']);
        return $this->success([
            'items' => $model->payments,
            'total' => $model->payments->count()
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function students($id): JsonResponse
    {
        $model = $this->repository->findOrFail($id, ['students', 'students.payments']);
        return $this->success([
            'items' => $model->students,
            'total' => $model->students->count()
        ]);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'cost' => 'required',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
            'description' => 'nullable|max:1000',
        ]);
    }

}
