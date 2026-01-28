<?php

namespace Modules\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Common\Entities\Outlay;
use Modules\Common\Repositories\Outlay\OutlayRepositoryInterface;
use Modules\Shop\Entities\Order;
use function PHPUnit\Framework\isEmpty;

class OutlayController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param OutlayRepositoryInterface $repository
     */
    public function __construct(OutlayRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'amount' => $model->amount,
            'date' => $model->date,
            'invoice' => $model->invoice,
            'tax_number' => $model->tax_number,
            'payment_method_id' => $model->payment_method_id,
            'media' => MediaResource::collection($model->media),
            'notes' => $model->notes,
            'type' => $model->type,
            'sub_type' => $model->sub_type,
            'total_amount' => $model->total_amount,
            'tax' => $model->tax
        ]);
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'name','sub_type'];
    }



    public function store(): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->create($data);
        if ($model->payment_method_id){
            $model->transactions()->create([
                'transaction_id' => Str::uuid(),
                'transactionable_id'=>$model->id,
                'transactionable_type' =>Outlay::class,
                'note' => $model->notes,
                'type' => 'withdraw',
                'amount' => $model->amount,
                'commission' => 0,
                'shipping' => 0,
                'total_amount' => $model->amount,
                'payment_method_id' => $model->payment_method_id,
                'created_by' => auth()->id()
            ]);
        }
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }

    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        if ($model->payment_method_id){
            if (count($model->transactions) == 0){
                $model->transactions()->create([
                    'transaction_id' => Str::uuid(),
                    'transactionable_id'=>$model->id,
                    'transactionable_type' =>Outlay::class,
                    'note' => $model->notes,
                    'type' => 'withdraw',
                    'amount' => $model->amount,
                    'commission' => 0,
                    'shipping' => 0,
                    'total_amount' => $model->amount,
                    'payment_method_id' => $model->payment_method_id,
                    'created_by' => auth()->id()
                ]);
            }else{
                $transcation = $model->transactions()->first();
                $transcation->update([
                    'note' => $model->notes,
                    'type' => 'withdraw',
                    'amount' => $model->amount,
                    'total_amount' => $model->amount,
                    'payment_method_id' => $model->payment_method_id,
                    'updated_by' => auth()->id()
                ]);
            }

        }else{
            if (count($model->transactions) > 0){
                $model->transactions()->update([
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now() // If using soft deletes
                ]);
            }
        }
        $model->syncMedia($data['media'] ?? []);
        return $this->success(
            $model
        );
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required|max:255',
            'type' => 'required',
            'date' => 'required|date',
            'amount' => 'required',
            'total_amount' => 'required',
            'tax' => 'nullable',
            'sub_type' => 'required',
            'invoice' => 'nullable',
            'tax_number' => 'nullable',
            'payment_method_id' => 'nullable',
            'notes' => 'nullable',
            'media' => 'nullable|array',
        ]);
    }

}
