<?php

namespace Modules\Common\Repositories\Links;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Link;
use Modules\Common\Entities\Promotion;

/**
 * Class PromotionRepository
 * @package Modules\Common\Repositories\Links
 */
class LinksRepository extends EloquentRepository implements LinksRepositoryInterface
{

    /**
     * PromotionRepository constructor.
     * @param Promotion $model
     */
    public function __construct(Link $model)
    {
        parent::__construct($model);
    }

}
