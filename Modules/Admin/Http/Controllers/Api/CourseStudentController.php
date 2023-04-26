<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Common\Repositories\CourseStudent\CourseStudentRepositoryInterface;

class CourseStudentController extends Controller
{

    /**
     * @var CourseStudentRepositoryInterface
     */
    private CourseStudentRepositoryInterface $repository;

    /**
     * CourseStudentController constructor.
     * @param CourseStudentRepositoryInterface $repository
     */
    public function __construct(CourseStudentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $data = $this->repository->get();
        return response()->json([
            'data' => $data
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $model = $this->repository->findOrFail($id, ['course']);
        return response()->json([
            'data' => $model
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $data = $this->repository->datatable([
            'id', 'name'
        ]);
        return response()->json($data);
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $model = $this->repository->create(
            $this->validate()
        );
        return response()->json([
            'data' => $model
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $model = $this->repository->update(
            $id,
            $this->validate()
        );
        return response()->json([
            'data' => $model
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $this->repository->delete($id);
        return response()->json();
    }

    /**
     * @return array
     */
    private function validate(): array
    {
        return request()->validate([
            'course_id' => 'required',
            'name' => 'required|max:255',
            'phone' => 'nullable',
            'email' => 'nullable|email',
            'notes' => 'nullable|max:1000',
        ]);
    }

}
