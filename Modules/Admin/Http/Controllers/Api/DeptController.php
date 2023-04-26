<?php


namespace Modules\Admin\Http\Controllers\Api;

use Modules\Common\Repositories\Dept\DeptRepositoryInterface;

class DeptController extends ApiAdminController
{

    /**
     * DeptController constructor.
     * @param DeptRepositoryInterface $repository
     */
    public function __construct(DeptRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }


    public function datatableSearchFields(): array
    {
        return [
            'id', 'name'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'date' => 'required|date',
            'amount' => 'required',
            'notes' => 'nullable|max:500',
            'paid' => 'nullable|boolean',
        ]);
    }

}
