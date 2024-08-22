<?php

namespace Modules\Shop\Repositories\Invoice;

use App\Repositories\Base\BaseRepository;
use Modules\Shop\Entities\Address;
use Modules\Shop\Entities\Invoice;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface InvoiceRepositoryInterface extends BaseRepository
{

    /**
     * @param array $data
     * @param bool $checkStock
     * @return Invoice
     */
    public function make(array $data): Invoice;






    public function get($wheres = [], $with = [],$orWhere = []);



}
