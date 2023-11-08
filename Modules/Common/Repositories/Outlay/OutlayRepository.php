<?php

namespace Modules\Common\Repositories\Outlay;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Outlay;

/**
 * Class CustomsStatementRepository
 * @package Modules\Shop\Repositories\Category
 */
class OutlayRepository extends EloquentRepository implements OutlayRepositoryInterface
{

    /**
     * CustomsStatementRepository constructor.
     * @param Outlay $model
     */
    public function __construct(Outlay $model)
    {
        parent::__construct($model);
    }

}
