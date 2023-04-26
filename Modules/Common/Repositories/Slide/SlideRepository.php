<?php

namespace Modules\Common\Repositories\Slide;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Slide;

/**
 * Class SlideRepository
 * @package Modules\Common\Repositories\Slide
 */
class SlideRepository extends EloquentRepository implements SlideRepositoryInterface
{

    /**
     * SlideRepository constructor.
     * @param Slide $model
     */
    public function __construct(Slide $model)
    {
        parent::__construct($model);
    }

}
