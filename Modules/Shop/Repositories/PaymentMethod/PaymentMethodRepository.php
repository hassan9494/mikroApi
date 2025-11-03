<?php

namespace Modules\Shop\Repositories\PaymentMethod;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\PaymentMethod;

/**
 * Class LocationRepository
 * @package Modules\Shop\Repositories\Location
 */
class PaymentMethodRepository extends EloquentRepository implements PaymentMethodRepositoryInterface
{

    /**
     * @var PaymentMethod
     */
    protected $model;

    /**
     * LocationRepository constructor.
     * @param PaymentMethod $model
     */
    public function __construct(PaymentMethod $model)
    {
        parent::__construct($model);
    }


}
