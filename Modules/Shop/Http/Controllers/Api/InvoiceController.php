<?php

namespace Modules\Shop\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Shop\Http\Resources\InvoiceResource;
use Modules\Shop\Repositories\Invoice\InvoiceRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

class InvoiceController extends Controller
{
    use ApiResponser;

    /**
     * @var InvoiceRepositoryInterface
     */
    private InvoiceRepositoryInterface $repository;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $products;

    /**
     * InvoiceController constructor.
     * @param InvoiceRepositoryInterface $repository
     * @param ProductRepositoryInterface $products
     */
    public function __construct(
        InvoiceRepositoryInterface $repository,
        ProductRepositoryInterface $products
    )
    {
        $this->repository = $repository;
        $this->products = $products;
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $invoices = $this->repository->get();
        return InvoiceResource::collection($invoices);
    }
}
