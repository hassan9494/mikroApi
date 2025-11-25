<?php

namespace Modules\Shop\Repositories\Transaction;

use App\Repositories\Base\EloquentRepository;
use Illuminate\Support\Str;
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

    public function create($data)
    {
        if (isset($data['order_id'])){
            $data['type'] = 'deposit';
            $data['total_amount'] = $data['amount'] - $data['commission'];
        }else{
            $data['total_amount'] = $data['amount'];
        }
        $data['transaction_id'] = Str::uuid();
        $data['note'] = '';

        $model = parent::create($data);
        return $model;
    }

    public function update($id,$data)
    {
        if ($data['commission']){
            $data['total_amount'] = $data['amount'] - $data['commission'];
        }else{
            $data['total_amount'] = $data['amount'];
        }

        $model = parent::update($id, $data);
        return $model;
    }


}
