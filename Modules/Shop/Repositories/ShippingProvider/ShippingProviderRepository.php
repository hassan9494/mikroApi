<?php

namespace Modules\Shop\Repositories\ShippingProvider;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\ShippingProvider;

/**
 * Class ShippingProviderRepository
 * @package Modules\Shop\Repositories\ShippingProvider
 */
class ShippingProviderRepository extends EloquentRepository implements ShippingProviderRepositoryInterface
{

    /**
     * ShippingProviderRepository constructor.
     * @param ShippingProvider $model
     */
    public function __construct(ShippingProvider $model)
    {
        parent::__construct($model);
    }

}
