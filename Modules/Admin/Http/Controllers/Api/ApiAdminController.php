<?php


namespace Modules\Admin\Http\Controllers\Api;

use App\Repositories\Base\BaseRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiAdminController extends Controller
{

    use ApiResponser;

    /**
     * @var BaseRepository $repository
     */
    protected $repository;


    /**
     * ApiAdminController constructor.
     * @param BaseRepository $repository
     */
    public function __construct(BaseRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return JsonResponse
     */
    public function index()
    {
        $data = $this->repository->get();
        return $this->success($data);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success($model);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $search = $this->datatableSearchFields();
        return $this->success(
            $this->repository->datatable($search)
        );
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $data = $this->validate();
        return $this->success(
            $this->repository->create($data)
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $data = $this->validate();
        return $this->success(
            $this->repository->update($id, $data)
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $this->repository->delete($id);
        return $this->success();
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([]);
    }

    /**
     * @return array
     */
    public function datatableSearchFields(): array
    {
        return [];
    }

}
