<?php

namespace Modules\Common\Repositories\CustomsStatement;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\CustomsStatements;

/**
 * Class CustomsStatementRepository
 * @package Modules\Shop\Repositories\Category
 */
class CustomsStatementRepository extends EloquentRepository implements CustomsStatementRepositoryInterface
{

    /**
     * CustomsStatementRepository constructor.
     * @param CustomsStatements $model
     */
    public function __construct(CustomsStatements $model)
    {
        parent::__construct($model);
    }

}
