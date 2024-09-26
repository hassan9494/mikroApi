<?php

namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Common\Entities\Tag;

class TagController extends Controller
{
    use ApiResponser;

    /**
     * @var Tag
     */
    private Tag $repository;

    /**
     * AddressController constructor.
     * @param Tag $repository
     */
    public function __construct(Tag $repository)
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
