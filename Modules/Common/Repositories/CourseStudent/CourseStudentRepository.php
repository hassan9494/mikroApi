<?php

namespace Modules\Common\Repositories\CourseStudent;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\CourseStudent;

/**
 * Class CourseStudentRepository
 * @package Modules\Shop\Repositories\Category
 */
class CourseStudentRepository extends EloquentRepository implements CourseStudentRepositoryInterface
{

    /**
     * CourseStudentRepository constructor.
     * @param CourseStudent $model
     */
    public function __construct(CourseStudent $model)
    {
        parent::__construct($model);
    }

}
