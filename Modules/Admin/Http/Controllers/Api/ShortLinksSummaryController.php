<?php


namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\ShortLinkClicksResource;
use Modules\Admin\Http\Resources\ShortLinkResource;
use Modules\Common\Entities\ShortLink;
use Modules\Common\Entities\ShortLinkClickSummary;
use Modules\Common\Repositories\ShortLinkClickSummary\ShortLinkClickSummaryRepositoryInterface;
use Psy\Util\Str;
use Illuminate\Http\Request;

class ShortLinksSummaryController extends ApiAdminController
{

    /**
     * PromotionRepositoryInterface constructor.
     * @param ShortLinkClickSummaryRepositoryInterface $repository
     */
    public function __construct(ShortLinkClickSummaryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }



    public function show($id)
    {

        $model = $this->repository->findOrFail($id);
        return new ShortLinkClicksResource($model);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {

        return Datatable::make($this->repository->model())
            ->search('id', 'country', 'ip_address')

            ->resource(ShortLinkClicksResource::class)
            ->json();
    }

}
