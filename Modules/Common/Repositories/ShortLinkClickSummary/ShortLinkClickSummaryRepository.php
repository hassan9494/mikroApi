<?php

namespace Modules\Common\Repositories\ShortLinkClickSummary;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\ShortLinkClickSummary;

/**
 * Class PromotionRepository
 * @package Modules\Common\Repositories\Links
 */
class ShortLinkClickSummaryRepository extends EloquentRepository implements ShortLinkClickSummaryRepositoryInterface
{

    /**
     * PromotionRepository constructor.
     * @param ShortLinkClickSummary $model
     */
    public function __construct(ShortLinkClickSummary $model)
    {
        parent::__construct($model);
    }

}
