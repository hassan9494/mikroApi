<?php

namespace Modules\Common\Repositories\Receipt;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Receipt;

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

}
