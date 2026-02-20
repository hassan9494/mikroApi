<?php

namespace Modules\Shop\Repositories\StockClose;

use App\Repositories\Base\EloquentRepository;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Shop\Entities\StockClose;

class StockCloseRepository extends EloquentRepository implements StockCloseRepositoryInterface
{
    public function __construct(StockClose $model)
    {
        parent::__construct($model);
    }

}
