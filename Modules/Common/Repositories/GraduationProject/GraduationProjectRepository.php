<?php

namespace Modules\Common\Repositories\GraduationProject;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\GraduationProject;

/**
 * Class GraduationProjectRepository
 * @package Modules\Shop\Repositories\Category
 */
class GraduationProjectRepository extends EloquentRepository implements GraduationProjectRepositoryInterface
{

    /**
     * GraduationProjectRepository constructor.
     * @param GraduationProject $model
     */
    public function __construct(GraduationProject $model)
    {
        parent::__construct($model);
    }

}
