<?php

namespace Modules\Admin\Http\Controllers\Api;

use Modules\Common\Repositories\Receipt\ReceiptRepositoryInterface;

class ReceiptController extends ApiAdminController
{

    /**
     * ReceiptController constructor.
     * @param ReceiptRepositoryInterface $repository
     */
    public function __construct(ReceiptRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'name'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'course_student_id' => 'nullable',
            'graduation_project_id' => 'nullable',
            'name' => 'required|max:255',
            'amount' => 'required',
            'date' => 'required|date',
            'explanation' => 'nullable',
            'notes' => 'nullable',
            'type' => 'required',
            'check_number' => 'nullable'
        ]);
    }

}
