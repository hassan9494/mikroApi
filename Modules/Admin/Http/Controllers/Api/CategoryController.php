<?php


namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Shop\Http\Resources\CategoryResource;
use Modules\Shop\Http\Resources\SubCategoryResource;
use Modules\Shop\Repositories\Category\CategoryRepositoryInterface;

;

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

    public function parentCategory()
    {
        $where[] = [
            'parent', '=', 0
        ];
        return response()->json([
            'status' => 'Success',
            'message' => null,
            'data' => $this->repository->get($where)
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'title', 'slug')

            ->resource(CategoryResource::class)
            ->json();
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

    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }

    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'parent' => $model->parent,
            'title' => $model->title,
            'icon' => $model->icon,
            'slug' => $model->slug,
            'order' => $model->order,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'deleted_at' => $model->deleted_at,
            'media' => MediaResource::collection($model->media),
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function subCategory(): JsonResponse
    {
        $whereHas['parentCategory'] = function ($q) {

        };
        $data = $this->repository->model();
        return Datatable::make($data)
            ->whereHas($whereHas)
            ->resource(SubCategoryResource::class)
            ->json();
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
            'media' => 'nullable|array',
        ]);
    }

}
