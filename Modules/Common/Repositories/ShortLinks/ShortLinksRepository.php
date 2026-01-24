<?php

namespace Modules\Common\Repositories\ShortLinks;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\ShortLink;

/**
 * Class PromotionRepository
 * @package Modules\Common\Repositories\Links
 */
class ShortLinksRepository extends EloquentRepository implements ShortLinksRepositoryInterface
{

    /**
     * PromotionRepository constructor.
     * @param ShortLink $model
     */
    public function __construct(ShortLink $model)
    {
        parent::__construct($model);
    }

}
