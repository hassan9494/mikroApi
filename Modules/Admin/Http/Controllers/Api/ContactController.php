<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Shop\Repositories\Contact\ContactRepositoryInterface;
use Modules\Shop\Http\Resources\ContactResource;

class ContactController extends ApiAdminController
{
    public function __construct(ContactRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * For select dropdowns
     */
    public function select(): JsonResponse
    {
        $data = $this->repository->pluck('name');
        return response()->json(['data' => $data]);
    }

    /**
     * Fields that can be searched in datatable
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'name', 'phone', 'email', 'company'];
    }

    /**
     * Validation rules for store/update
     */
    public function validate(): array
    {
        return request()->validate([
            'name'    => 'required|max:255',
            'phone'   => 'nullable|string|max:50',
            'email'   => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'fax'     => 'nullable|string|max:50',
            'sub'     => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'other'   => 'nullable|string|max:1000',
            'note'    => 'nullable|string|max:1000',
        ]);
    }

    public function index()
    {
        if (request()->has('all') && request()->boolean('all')) {
            $contacts = $this->repository->all(); // returns all records without pagination
            return ContactResource::collection($contacts); // returns { data: [...] }
        }
        return parent::index();
    }

}
