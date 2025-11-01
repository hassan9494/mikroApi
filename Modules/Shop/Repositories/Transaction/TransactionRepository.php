<?php

namespace Modules\Shop\Repositories\Transaction;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Transaction;

/**
 * Class LocationRepository
 * @package Modules\Shop\Repositories\Transaction
 */
class TransactionRepository extends EloquentRepository implements TransactionRepositoryInterface
{

    /**
     * @var Transaction
     */
    protected $model;

    /**
     * LocationRepository constructor.
     * @param Transaction $model
     */
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }


}
