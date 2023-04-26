<?php

namespace Modules\Common\Repositories\City;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\City;

/**
 * Class CityRepository
 * @package Modules\Common\Repositories\City
 */
class CityRepository extends EloquentRepository implements CityRepositoryInterface
{

    /**
     * CityRepository constructor.
     * @param City $model
     */
    public function __construct(City $model)
    {
        parent::__construct($model);
    }

}
