<?php

namespace Modules\Shop\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use App\Traits\ApiResponser;
use Modules\Common\Entities\City;

class CityController extends Controller
{

    use ApiResponser;

    /**
     * @var City
     */
    private City $repository;

    /**
     * AddressController constructor.
     * @param City $repository
     */
    public function __construct(City $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $data = $this->repository->all();
        return $this->success($data);
    }


}
