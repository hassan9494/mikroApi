<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Common\Repositories\GraduationProject\GraduationProjectRepositoryInterface;
use Modules\Common\Repositories\ProjectReceipt\ProjectReceiptRepositoryInterface;

class GraduationProjectController extends ApiAdminController
{
    public ProjectReceiptRepositoryInterface $recieptRepository;
    /**
     * GraduationProjectController constructor.
     * @param GraduationProjectRepositoryInterface $repository
     * @param ProjectReceiptRepositoryInterface $recieptRepository
     */
    public function __construct(GraduationProjectRepositoryInterface $repository,ProjectReceiptRepositoryInterface $recieptRepository)
    {
        $this->repository = $repository;
        $this->recieptRepository = $recieptRepository;
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
    public function paymentTransaction($id): JsonResponse
    {
        $model = $this->recieptRepository->findOrFail($id, ['transactions']);
        return $this->success([
            'items' => $model->transactions,
            'total' => $model->transactions->count()
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
