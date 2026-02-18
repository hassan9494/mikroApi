<?php

namespace Modules\Common\Repositories\ProjectReceipt;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\ProjectReceipt;
use Modules\Shop\Entities\PaymentMethod;

/**
 * Class ReceiptRepository
 * @package Modules\Shop\Repositories\Category
 */
class ProjectReceiptRepository extends EloquentRepository implements ProjectReceiptRepositoryInterface
{

    /**
     * ReceiptRepository constructor.
     * @param ProjectReceipt $model
     */
    public function __construct(ProjectReceipt $model)
    {
        parent::__construct($model);
    }

    public function create($data)
    {
        $paymentMethod = PaymentMethod::find($data['payment_method_id']);
        $data['type'] = $paymentMethod->name;

        $model = parent::create($data);
        return $model;
    }

    public function update($id, $data)
    {
        $model = $this->findOrFail($id);
        $model->update($data);
        $model->transactions()->update([
            'payment_method_id' => $data['payment_method_id'],
            'total_amount' => $data['amount'],
            'amount' => $data['amount'],
            'note' => $data['notes'] ?? '',
            'updated_by' => auth()->id(),
        ]);
        return $model;
    }

    public function delete($id)
    {
        $receipt = $this->findOrFail($id);

        // Update deleted_by field before deleting
        $receipt->transactions()->update([
            'deleted_by' => auth()->id(),
            'deleted_at' => now() // If using soft deletes
        ]);

        // Then delete
        $receipt->transactions()->delete();
        $receipt->delete();

        return true;
    }

}
