<?php


namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\ShippingProvider\ShippingProviderRepositoryInterface;

class ShippingProviderController extends ApiAdminController
{

    /**
     * ShippingProviderRepositoryInterface constructor.
     * @param ShippingProviderRepositoryInterface $repository
     */
    public function __construct(ShippingProviderRepositoryInterface $repository)
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
            'phone' => 'nullable',
            'email' => 'nullable',
            'notes' => 'nullable',
        ]);
    }

}
