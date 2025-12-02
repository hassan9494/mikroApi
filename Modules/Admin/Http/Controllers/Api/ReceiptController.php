<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\ReceiptResource;
use Modules\Common\Repositories\Receipt\ReceiptRepositoryInterface;
use Modules\Shop\Entities\Transaction;

class ReceiptController extends ApiAdminController
{

    /**
     * ReceiptController constructor.
     * @param ReceiptRepositoryInterface $repository
     */
    public function __construct(ReceiptRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }



    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'name', 'tax_number')
            ->resource(ReceiptResource::class)
            ->json();
    }

    public function store() : JsonResponse
    {
        $data = $this->validate();
        if (isset($data['transaction_id'])){
            $transcation = Transaction::find($data['transaction_id']);
            $receipt = $transcation->receipt;
            if ($receipt){
                $model = $this->repository->update($receipt->id, $data);
            }else{
                $model = $this->repository->create($data);
            }
        }else{
            $model = $this->repository->create($data);
        }

        return $this->success(
            $model
        );
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'name'];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'course_student_id' => 'nullable',
            'graduation_project_id' => 'nullable',
            'name' => 'required|max:255',
            'amount' => 'required',
            'date' => 'required|date',
            'explanation' => 'nullable',
            'notes' => 'nullable',
            'type' => 'nullable',
            'payment_method_id' => 'required',
            'transaction_id' => 'nullable',
            'check_number' => 'nullable'
        ]);
    }

}
