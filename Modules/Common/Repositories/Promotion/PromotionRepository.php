<?php

namespace Modules\Common\Repositories\Promotion;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Promotion;

/**
 * Class PromotionRepository
 * @package Modules\Common\Repositories\Promotion
 */
class PromotionRepository extends EloquentRepository implements PromotionRepositoryInterface
{

    /**
     * PromotionRepository constructor.
     * @param Promotion $model
     */
    public function __construct(Promotion $model)
    {
        parent::__construct($model);
    }

}
