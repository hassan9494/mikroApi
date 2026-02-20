<?php

namespace Modules\Shop\Repositories\StockCloseDetail;

use App\Repositories\Base\EloquentRepository;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Shop\Entities\StockClose;
use Modules\Shop\Entities\StockCloseDetail;

class StockCloseDetailRepository extends EloquentRepository implements StockCloseDetailRepositoryInterface
{
    public function __construct(StockCloseDetail $model)
    {
        parent::__construct($model);
    }

}
