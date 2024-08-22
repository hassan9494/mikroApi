<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\InvoiceResource;
use Modules\Shop\Repositories\Invoice\InvoiceRepositoryInterface;
use Modules\Shop\Support\Enums\InvoiceStatus;

class InvoiceController extends ApiAdminController
{

    /**
     * InvoiceController constructor.
     * @param InvoiceRepositoryInterface $repository
     */
    public function __construct(InvoiceRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @param $id
     * @return InvoiceResource
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return new InvoiceResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function sales(): JsonResponse
    {
        $from = request('from');
        $to = request('to');
        $id = json_decode(request('conditions'))[0]->id;
        $whereHas['Products'] =  function ($q) use ($id) {
            if ($id) $q->where('products.id',  $id);
        };
        return Datatable::make($this->repository->model())
            ->with(['products'])
            ->whereHas($whereHas)
            ->search('id', 'name', 'sku')
            ->resource(InvoiceResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();

        $invoice = $this->repository->make($data);
            // Mark as processing
            $this->repository->status($invoice->id, InvoiceStatus::DRAFT()->value);


        $invoice->syncMedia($data['attachments'] ?? []);

        return $this->success([
            'id' => $invoice->id
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $invoice = $this->repository->saveInvoice($id, $data);
        $invoice->syncMedia($data['attachments'] ?? []);
        return $this->success();
    }

    /**
     * @param $id
     */
    public function status($id)
    {
        $this->repository->status(
            $id, request()->get('status')
        );
    }


    /**
     * @param $id
     */
    public function shippingStatus($id)
    {
        $this->repository->update(
            $id, request()->only('shipping_status')
        );
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return [
            'id', 'customer->name', 'customer->email', 'customer->phone'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'note' => 'nullable|max:500',
            'number' => 'nullable|max:500',


            'products.*.id' => 'exists:products,id',
            'products.*.price' => 'required|numeric',
            'products.*.quantity' => 'required|numeric',

            'attachments' => 'nullable|array',
        ]);
    }

}
