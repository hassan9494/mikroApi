<?php

namespace Modules\Shop\Repositories\Address;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Address;

/**
 * Class AddressRepository
 * @package Modules\Shop\Repositories\Address
 */
class AddressRepository extends EloquentRepository implements AddressRepositoryInterface
{

    /**
     * AddressRepository constructor.
     * @param Address $model
     */
    public function __construct(Address $model)
    {
        parent::__construct($model);
    }

}
