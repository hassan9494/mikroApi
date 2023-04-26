<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Common\Repositories\City\CityRepositoryInterface;

class CityController extends ApiAdminController
{

    /**
     * CityRepositoryInterface constructor.
     * @param CityRepositoryInterface $repository
     */
    public function __construct(CityRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck();
        return $this->success($data);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'shipping_cost' => 'required',
        ]);
    }

}
