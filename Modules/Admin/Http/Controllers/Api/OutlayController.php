<?php

namespace Modules\Admin\Http\Controllers\Api;

use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;

class OutlayController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param OutlayRepositoryInterface $repository
     */
    public function __construct(OutlayRepositoryInterface $repository)
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
            'name' => 'required|max:255',
            'type' => 'required',
            'date' => 'required|date',
            'amount' => 'required',
            'invoice' => 'nullable',
            'notes' => 'nullable'
        ]);
    }

}
