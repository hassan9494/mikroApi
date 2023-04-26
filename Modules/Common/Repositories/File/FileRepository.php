<?php

namespace Modules\Common\Repositories\File;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\File;

/**
 * Class FileRepository
 * @package Modules\Common\Repositories\File
 */
class FileRepository extends EloquentRepository implements FileRepositoryInterface
{

    /**
     * FileRepository constructor.
     * @param File $model
     */
    public function __construct(File $model)
    {
        parent::__construct($model);
    }

}
