<?php

namespace Modules\Common\Repositories\Receipt;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Receipt;
use Modules\Shop\Entities\PaymentMethod;

/**
 * Class ReceiptRepository
 * @package Modules\Shop\Repositories\Category
 */
class ReceiptRepository extends EloquentRepository implements ReceiptRepositoryInterface
{

    /**
     * ReceiptRepository constructor.
     * @param Receipt $model
     */
    public function __construct(Receipt $model)
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

}
