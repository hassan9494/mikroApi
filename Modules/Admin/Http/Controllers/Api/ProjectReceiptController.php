<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Admin\Http\Resources\ReceiptResource;
use Modules\Common\Entities\ProjectReceipt;
use Modules\Common\Repositories\ProjectReceipt\ProjectReceiptRepositoryInterface;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\Transaction;
use SebastianBergmann\CodeCoverage\Report\Xml\Project;

class ProjectReceiptController extends ApiAdminController
{

    /**
     * ReceiptController constructor.
     * @param ProjectReceiptRepositoryInterface $repository
     */
    public function __construct(ProjectReceiptRepositoryInterface $repository)
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
            $receipt = $transcation->projectReceipt;
            if ($receipt){
                $model = $this->repository->update($receipt->id, $data);
            }else{
                $model = $this->repository->create($data);
            }
        }else{
            $model = $this->repository->create($data);
            $model->transactions()->create([
                'transaction_id' => Str::uuid(),
                'transactionable_id'=>$model->id,
                'transactionable_type' =>ProjectReceipt::class,
                'note' =>request()->get('notes') ?? '',
                'type' => 'deposit',
                'amount' => request()->get('amount'),
                'commission' => 0,
                'shipping' => 0,
                'total_amount' => request()->get('amount'),
                'payment_method_id' => request()->get('payment_method_id'),
                'created_by' => auth()->id()
            ]);
        }

        return $this->success(
            $model
        );
    }


    public function storeTransaction() : JsonResponse
    {
        $data = $this->validateTransaction();
        if (isset($data['project_receipt_id'])){
            $model = $this->repository->findOrFail($data['project_receipt_id']);
            $model->transactions()->create([
                'transaction_id' => Str::uuid(),
                'transactionable_id'=>$model->id,
                'transactionable_type' =>ProjectReceipt::class,
                'note' =>request()->get('notes') ?? '',
                'type' => $data['type'],
                'amount' => request()->get('amount'),
                'commission' => 0,
                'shipping' => 0,
                'total_amount' => request()->get('amount'),
                'payment_method_id' => request()->get('payment_method_id'),
                'created_by' => auth()->id()
            ]);
        }

        return $this->success(
            $model
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroyTransaction($id): JsonResponse
    {
        $transaction = Transaction::findOrFail($id);
        $transaction->delete($id);
        return $this->success();
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
            'graduation_project_id' => 'required',
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

    /**
     * @return array
     */
    public function validateTransaction(): array
    {
        return request()->validate([
            'amount' => 'required',
            'notes' => 'nullable',
            'payment_method_id' => 'required',
            'project_receipt_id' => 'required',
            'type' => 'required',
        ]);
    }

}
