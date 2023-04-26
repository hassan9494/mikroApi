<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Category\CategoryRepositoryInterface;

class CategoryController extends ApiAdminController
{

    /**
     * CategoryController constructor.
     * @param CategoryRepositoryInterface $repository
     */
    public function __construct(CategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck('title');
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'title', 'slug'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'title' => 'required|max:255',
            'slug' => 'required|max:255',
            'icon' => 'nullable|max:255',
            'parent' => 'nullable',
            'order' => 'required',
        ]);
    }

}
