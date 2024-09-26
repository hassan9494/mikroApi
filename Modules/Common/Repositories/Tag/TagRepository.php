<?php

namespace Modules\Common\Repositories\Tag;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Tag;

/**
 * Class SlideRepository
 * @package Modules\Common\Repositories\Slide
 */

class TagRepository extends EloquentRepository implements TagRepositoryInterface
{
    /**
     * SlideRepository constructor.
     * @param Tag $model
     */
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }
}
