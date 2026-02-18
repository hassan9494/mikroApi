<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Source\SourceRepositoryInterface;

class SourceController extends ApiAdminController
{
    /**
     * CategoryController constructor.
     * @param SourceRepositoryInterface $repository
     */
    public function __construct(SourceRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck('name');
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'name', 'slug'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'slug' => 'required|max:255',
            'order' => 'required',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'note'  => 'nullable|string|max:1000',

        ]);
    }
}
