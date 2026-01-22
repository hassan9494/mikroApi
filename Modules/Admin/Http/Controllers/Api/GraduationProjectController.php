<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Common\Repositories\GraduationProject\GraduationProjectRepositoryInterface;

class GraduationProjectController extends ApiAdminController
{

    /**
     * GraduationProjectController constructor.
     * @param GraduationProjectRepositoryInterface $repository
     */
    public function __construct(GraduationProjectRepositoryInterface $repository)
    {
        parent::__construct($repository);
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
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'implementer_name' => 'required|max:255',
            'cost' => 'required',
            'deadline' => 'nullable|date',
            'description' => 'nullable|max:500',
            'students' => 'nullable|max:500',
            'notes' => 'nullable|max:500',
            'completed' => 'boolean ',
        ]);
    }

}
