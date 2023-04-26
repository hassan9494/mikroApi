<?php

namespace Modules\Common\Repositories\Course;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Course;

/**
 * Class CourseRepository
 * @package Modules\Shop\Repositories\Category
 */
class CourseRepository extends EloquentRepository implements CourseRepositoryInterface
{

    /**
     * CourseRepository constructor.
     * @param Course $model
     */
    public function __construct(Course $model)
    {
        parent::__construct($model);
    }

}
