<?php

namespace Modules\Shop\Repositories\Product;

use App\Repositories\Base\BaseRepository;
use Illuminate\Support\Collection;
use Exception;
use Modules\Shop\Entities\Product;
use Spatie\Permission\Models\Role;

/**
 * Interface DeviceRepository
 * @package App\Repositories\Base
 */
interface ProductRepositoryInterface extends BaseRepository
{

    public function search($searchWord, $category, $limit = 20);

    public function autocomplete($searchWord, $limit = 20);

}
