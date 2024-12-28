<?php

namespace Modules\Shop\Repositories\Location;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Location;

/**
 * Class LocationRepository
 * @package Modules\Shop\Repositories\Location
 */
class LocationRepository extends EloquentRepository implements LocationRepositoryInterface
{

    /**
     * @var Location
     */
    protected $model;

    /**
     * LocationRepository constructor.
     * @param Location $model
     */
    public function __construct(Location $model)
    {
        parent::__construct($model);
    }


}
