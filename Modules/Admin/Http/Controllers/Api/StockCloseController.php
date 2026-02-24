<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Jobs\StoreStockCloseDetailsJob;
use App\Traits\ApiResponser;
use App\Traits\Datatable;
use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\StockCloseDetailResource;
use Modules\Admin\Http\Resources\StockCloseResource;
use Modules\Shop\Entities\StockCloseDetail;
use Modules\Shop\Repositories\StockClose\StockCloseRepositoryInterface;
use Modules\Shop\Repositories\StockCloseDetail\StockCloseDetailRepositoryInterface;

class StockCloseController extends ApiAdminController
{

//    /**
//     * @var StockCloseRepositoryInterface
//     */
//    protected StockCloseRepositoryInterface $repository;

    /**
     * @var StockCloseDetailRepositoryInterface
     */
    private StockCloseDetailRepositoryInterface $detailRepository;


    /**
     * InvoiceController constructor.
     * @param StockCloseRepositoryInterface $repository
     * @param StockCloseDetailRepositoryInterface $detailRepository
     */
    public function __construct(StockCloseRepositoryInterface $repository,StockCloseDetailRepositoryInterface $detailRepository)
    {
        $this->repository = $repository;
        $this->detailRepository = $detailRepository;
    }

    /**
     * @param $id
     * @return StockCloseResource
     */
    public function show($id)
    {
        $user = auth()->user();
        $model = $this->repository->findOrFail($id);

        return new StockCloseResource($model);
    }

    public function detailsDatatable(): JsonResponse
    {
        return Datatable::make($this->detailRepository->model())
            ->search($this->detailDatatableSearchFields())
            ->resource(StockCloseDetailResource::class)
            ->json();
    }

    public function details()
    {
        $stock_close_id = request('stock_close_id');
        $data = StockCloseDetail::where('stock_close_id',$stock_close_id)->get();
        return StockCloseDetailResource::collection($data);
    }

    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {

        $data = $this->validate();
        $user = auth()->user();
        $data['user_id'] = $user->id;
        $stockClose = $this->repository->create($data);
        StoreStockCloseDetailsJob::dispatch($stockClose->id);
        return $this->success([
            'id' => $stockClose->id
        ]);
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return [
            'id', 'name', 'user_id'
        ];
    }

    /**
     * @return string[]
     */
    public function detailDatatableSearchFields(): array
    {
        return [
            'id', 'product_id', 'product_name'
        ];
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $data = request()->validate([
            'notes' => 'nullable|max:500',
            'name' => 'nullable|max:500',
            'date' => 'nullable',
        ]);

        return $data;
    }

}
