<?php

namespace Modules\Common\Repositories\Outlay;

use App\Repositories\Base\EloquentRepository;
use Modules\Common\Entities\Outlay;

/**
 * Class CustomsStatementRepository
 * @package Modules\Shop\Repositories\Category
 */
class OutlayRepository extends EloquentRepository implements OutlayRepositoryInterface
{

    /**
     * CustomsStatementRepository constructor.
     * @param Outlay $model
     */
    public function __construct(Outlay $model)
    {
        parent::__construct($model);
    }

    public function delete($id)
    {
        $outlay = $this->findOrFail($id);

        // Update deleted_by field before deleting
        $outlay->transactions()->update([
            'deleted_by' => auth()->id(),
            'deleted_at' => now() // If using soft deletes
        ]);

        // Then delete
        $outlay->transactions()->delete();
        $outlay->delete();

        return true;
    }

}
