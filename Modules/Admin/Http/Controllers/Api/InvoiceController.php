<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Traits\Datatable;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;
use Modules\Admin\Http\Resources\DatatableProductResource;
use Modules\Admin\Http\Resources\InvoiceResource;
use Modules\Shop\Repositories\Invoice\InvoiceRepositoryInterface;
use Modules\Shop\Support\Enums\InvoiceStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

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
        $user = auth()->user();
        $canShowComplete = $user->hasPermissionTo('show_completed_invoices','web');
        $model = $this->repository->findOrFail($id);
        if ($model->status == 'COMPLETED'){
            if (!$canShowComplete){
                return \response()->json([
                    'data' => null,
                    'message' => 'test',
                    'code' => 401
                ],403);
            }
        }
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
            'id', 'name', 'number'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $data = request()->validate([
            'note' => 'nullable|max:500',
            'number' => 'nullable|max:500',
            'tax_number' => 'nullable|numeric',
            'exchange_factor' => 'nullable|numeric',
            'name' => 'nullable|max:500',
            'date' => 'nullable',
            'products.*.id' => 'exists:products,id',
            'products.*.purchases_price' => 'required|numeric',
            'products.*.exchange_factor' => 'required|numeric',
            'products.*.base_purchases_price' => 'required|numeric',
            'products.*.distributer_price' => 'required|numeric',
            'products.*.normal' => 'required|numeric',
            'products.*.sale_price' => 'required|numeric',
            'products.*.source_sku' => 'nullable|max:500',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.allocation' => 'required|in:store,stock,total',
            'products.*.stock_available_qty' => 'required|numeric|min:0',
            'products.*.store_available_qty' => 'required|numeric|min:0',
            'source_id' => 'nullable|integer',
            'attachments' => 'nullable|array',
        ]);

        // Add custom validation to ensure sum equals quantity
        foreach ($data['products'] ?? [] as $key => $product) {
            $stockQty = $product['stock_available_qty'] ?? 0;
            $storeQty = $product['store_available_qty'] ?? 0;
            $totalQty = $product['quantity'] ?? 0;

            if (($stockQty + $storeQty) != $totalQty) {
                throw ValidationException::withMessages([
                    "products.{$key}.stock_available_qty" => "Stock distribution must sum to total quantity",
                    "products.{$key}.store_available_qty" => "Stock distribution must sum to total quantity"
                ]);
            }
        }

        return $data;
    }

}
