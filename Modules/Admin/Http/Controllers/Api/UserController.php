<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Models\User;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PointTransaction;
use Modules\Shop\Http\Resources\OrderResource;
use Modules\Shop\Http\Resources\PointTransactionResource;

class UserController extends ApiAdminController
{

    /**
     * @param UserRepositoryInterface $repository
     */
    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        $search = $this->datatableSearchFields();
        $user = Auth::user();

        if ($user->hasRole(['super'])){
            $whereDosntHas = [];
        }elseif($user->hasRole(['admin']))
        {
            $whereDosntHas =  ['super'];
        }else{
            $whereDosntHas =  ['super','admin','Admin cash'];
        }

        $withCounts = ['orders', 'pointTransactions'];

        return $this->success(
            $this->repository->datatable($search, ['roles'], $whereDosntHas, [], $withCounts)
        );
    }

    /**
     * @return JsonResponse
     */
    public function employee(): JsonResponse
    {
        $search = $this->datatableSearchFields();
        $user = Auth::user();

        if ($user->hasRole(['super'])){
            $whereHas = ['super','admin','Admin cash','Distributer','Cashier','Product Manager','Manager', 'Stock Manager'];
        }elseif($user->hasRole(['admin']))
        {
            $whereHas =  ['admin','Admin cash','Distributer','Cashier','Product Manager','Manager', 'Stock Manager'];
        }else{
            $whereHas =  ['Admin cash','Distributer','Cashier','Product Manager','Manager', 'Stock Manager'];
        }

        $withCounts = ['orders', 'pointTransactions'];

        return $this->success(
            $this->repository->datatable($search, ['roles'], [], $whereHas, $withCounts)
        );
    }

    /**
     * @return string[]
     */
    public function datatableSearchFields(): array
    {
        return ['id', 'name', 'email', 'phone'];
    }


    /**
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $data = $this->validate();
        request()->validate([
            'password' => 'required|min:8|max:32|confirmed',
            'password_confirmation' => 'required',
        ]);
        if (request('password') == request('password_confirmation') ){
            $data['password'] = Hash::make(request('password'));
            return $this->success(
                $this->repository->create($data)
            );
        }else{
            abort(500, 'The password does not match the password confirm');
        }

    }


    /**
     * @param $id
     * @return JsonResponse
     */
    public function update($id): JsonResponse
    {
        if ($id == 1) abort(403, 'Please ask the manager to update this user!');
        $data = $this->validate();
        return $this->success(
            $this->repository->update($id, $data)
        );
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $model = $this->repository->findOrFail($id, 'roles');
        return $this->success($model);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $id = request()->route()->parameter('user');
        return request()->validate([
            'name' => 'required|max:255',
            'email' => 'required|unique:users,email,'.$id,
            'phone' => 'required',
            'status' => 'required|numeric',
            'roles' => 'required',
            'roles.*' => 'required|exists:roles,name',
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function autocomplete(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocomplete($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'taxExempt' => $model->taxExempt,
            ];
        }
        return $this->success($response);
    }

    public function autocompleteUserForTaxExempt(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocompleteUserForTaxExempt($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
                'taxExempt' => $model->taxExempt,
            ];
        }
        return $this->success($response);
    }

    /**
     * @return JsonResponse
     */
    public function autocompletecashier(): JsonResponse
    {
        $q = request()->get('q');
        $models = $this->repository->autocompletecashier($q);
        $response = [];
        foreach ($models as $model)
        {
            $response[] = [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'phone' => $model->phone,
            ];
        }
        return $this->success($response);
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function verificationEmail($id): JsonResponse
    {
        $user = $this->repository->findOrFail($id);
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification($id);
        }
        return $this->success();
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword($id, Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|min:8|max:32|confirmed',
            'password_confirmation' => 'required',
        ]);
        $this->repository->update($id, [
            'password'=> Hash::make($request->password)
        ]);
        return $this->success();
    }

    /**
     * Get user details with summary statistics
     *
     * @param int $id
     * @return JsonResponse
     */
    public function details(int $id): JsonResponse
    {
        $user = User::with(['roles'])
            ->withCount(['orders', 'pointTransactions'])
            ->findOrFail($id);

        // Calculate order totals
        $orderStats = Order::where('user_id', $id)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(total), 0) as orders_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = "completed" THEN total ELSE 0 END), 0) as completed_total')
            ->first();

        // Get points summary using PointService
        $pointService = app(PointService::class);
        $pointsSummary = $pointService->getUserSummary($id);

        return $this->success([
            'user' => $user,
            'stats' => [
                'orders_count' => $orderStats->total_orders ?? 0,
                'orders_total' => $orderStats->orders_total ?? 0,
                'completed_orders_total' => $orderStats->completed_total ?? 0,
                'points_balance' => $pointsSummary['available_balance'] ?? 0,
                'points_earned' => $pointsSummary['total_earned'] ?? 0,
                'points_spent' => $pointsSummary['total_spent'] ?? 0,
            ],
        ]);
    }

    /**
     * Get user orders with pagination
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function userOrders(int $id, Request $request): JsonResponse
    {
        $perPage = $request->get('limit', 10);
        $page = $request->get('page', 0) + 1;
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = Order::where('user_id', $id)->with(['products']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'items' => OrderResource::collection($orders->items()),
            'total' => $orders->total(),
        ]);
    }

    /**
     * Get user point transactions with pagination
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function userPoints(int $id, Request $request): JsonResponse
    {
        $perPage = $request->get('limit', 10);
        $page = $request->get('page', 0) + 1;
        $type = $request->get('type');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = PointTransaction::where('user_id', $id)->with(['order:id,status,total']);

        if ($type) {
            $query->where('type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'items' => PointTransactionResource::collection($transactions->items()),
            'total' => $transactions->total(),
        ]);
    }

}
