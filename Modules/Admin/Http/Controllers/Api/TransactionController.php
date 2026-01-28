<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Admin\Http\Resources\TransactionResource;
use Modules\Shop\Entities\ClosePoint;
use Modules\Shop\Entities\Transaction;
use Modules\Shop\Http\Resources\CategoryResource;
use Modules\Shop\Repositories\Transaction\TransactionRepositoryInterface;

class TransactionController extends ApiAdminController
{

    /**
     * OutlayController constructor.
     * @param TransactionRepositoryInterface $repository
     */
    public function __construct(TransactionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success(new TransactionResource($model));
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'transaction_id', 'order_id')
            ->resource(TransactionResource::class)
            ->json();
    }

    /**
     * @return JsonResponse
     */
    public function deletedDatatable()
    {
        $products = Transaction::onlyTrashed()->get();
        $total = Transaction::onlyTrashed()->count();
        $items = TransactionResource::collection($products);
        return ['data'=>['items'=>$items,'total'=>$total]];
    }


    public function totals(): JsonResponse
    {
        $today = now()->format('Y-m-d');
        $currentYear = now()->year;

        // Today's totals grouped by payment method
        $todayTotals = Transaction::whereDate('created_at', $today)
            ->selectRaw('payment_method_id,
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // This year's totals grouped by payment method
        $yearTotals = Transaction::whereYear('created_at', $currentYear)
            ->selectRaw('payment_method_id,
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // Get last close point (if exists)
        $lastClosePoint = ClosePoint::orderBy('close_date', 'desc')->first();

        // Since last close totals grouped by payment method
        $sinceLastCloseQuery = Transaction::query();

        if ($lastClosePoint) {
            // If there's a last close point, get transactions after it
            $sinceLastCloseQuery->where('created_at', '>', $lastClosePoint->close_date);
        } else {
            // If no close points, get from start of current year
            $sinceLastCloseQuery->where('created_at', '>=', now()->startOfYear());
        }

        $sinceLastCloseTotals = $sinceLastCloseQuery->clone()
            ->selectRaw('payment_method_id,
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // Overall totals (without grouping)
        $overallToday = Transaction::whereDate('created_at', $today)
            ->selectRaw('
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->first();

        $overallYear = Transaction::whereYear('created_at', $currentYear)
            ->selectRaw('
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->first();

        $overallSinceLastClose = $sinceLastCloseQuery->clone()
            ->selectRaw('
        SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
        SUM(CASE
            WHEN type = "deposit" THEN total_amount
            WHEN type = "withdraw" THEN -total_amount
            WHEN type = "refund" THEN -total_amount
        END) as net_amount,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount,
        SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount,
        SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount,
        SUM(CASE
            WHEN type = "deposit" THEN amount
            WHEN type = "withdraw" THEN -amount
            WHEN type = "refund" THEN -amount
        END) as remaining_deposit_amount,
        SUM(commission) as total_commission,
        SUM(shipping) as total_shipping')
            ->first();

        return response()->json([
            'data'=>[
                'today' => [
                    'grouped_by_payment_method' => $todayTotals,
                    'overall' => $overallToday
                ],
                'this_year' => [
                    'grouped_by_payment_method' => $yearTotals,
                    'overall' => $overallYear
                ],
                'since_last_close' => [
                    'grouped_by_payment_method' => $sinceLastCloseTotals,
                    'overall' => $overallSinceLastClose,
                    'last_close_date' => $lastClosePoint ? $lastClosePoint->close_date : null,
                    'has_close' => !is_null($lastClosePoint)
                ]
            ]
        ]);
    }

    public function filteredTotals(Request $request)
    {
        $query = Transaction::query();

        // Apply date filters
        if ($request->has('from') && $request->from) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to') && $request->to) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        // Apply type filter
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Apply payment method filter
        if ($request->has('payment_method_id') && $request->payment_method_id) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Get totals grouped by payment method - CORRECTED LOGIC
        $groupedTotals = $query->clone()
            ->selectRaw('payment_method_id,
            SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
            SUM(CASE
                WHEN type = "deposit" THEN total_amount
                WHEN type = "withdraw" THEN -total_amount
                WHEN type = "refund" THEN -total_amount
            END) as net_amount,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount_raw,
            SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount_raw,
            SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount_raw,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_deposit_amount, -- Only deposit amounts
            SUM(commission) as total_commission,
            SUM(shipping) as total_shipping')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // Get overall totals - CORRECTED LOGIC
        $overallTotals = $query->clone()
            ->selectRaw('
            SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
            SUM(CASE
                WHEN type = "deposit" THEN total_amount
                WHEN type = "withdraw" THEN -total_amount
                WHEN type = "refund" THEN -total_amount
            END) as net_amount,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount_raw,
            SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount_raw,
            SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount_raw,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_deposit_amount, -- Only deposit amounts
            SUM(commission) as total_commission,
            SUM(shipping) as total_shipping')
            ->first();

        return response()->json([
            'overall' => $overallTotals ?? (object)[
                    'total_deposits' => 0,
                    'total_withdrawals' => 0,
                    'total_refunds' => 0,
                    'net_amount' => 0,
                    'deposit_amount_raw' => 0,
                    'withdraw_amount_raw' => 0,
                    'refund_amount_raw' => 0,
                    'total_deposit_amount' => 0, // New field
                    'total_commission' => 0,
                    'total_shipping' => 0
                ],
            'grouped_by_payment_method' => $groupedTotals
        ]);
    }


    public function datatableSearchFields(): array
    {
        return ['id', 'transaction_id','order_id'];
    }



    public function store(): JsonResponse
    {
        $data = $this->validate();
        $data['created_by'] = auth()->id();
        $model = $this->repository->create($data);
        return $this->success(
            new TransactionResource($model)
        );
    }

    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $data['updated_by'] = auth()->id();
        $model = $this->repository->update($id, $data);
        return $this->success(
            $model
        );
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'amount' => 'required|numeric',
            'payment_method_id' => 'required',
            'type' => 'nullable',
            'order_id' => 'nullable',
            'return_order_id' => 'nullable',
            'commission' => 'nullable',
            'created_at' => 'nullable|date',
        ]);
    }

    /**
     * @param $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $transaction = Transaction::find($id);
        $transaction->update([
            'deleted_by' => auth()->id()
        ]);
        $this->repository->delete($id);
        return $this->success();
    }


    /**
     * Create a new close point
     */
    public function createClosePoint(Request $request)
    {
        $request->validate([
            'close_date' => 'nullable|date'
        ]);

        $closePoint = ClosePoint::create([
            'close_date' => $request->close_date ?? now(),
            'closed_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Close point created successfully',
            'data' => $closePoint
        ]);
    }

    /**
     * Get all close points
     */
    public function closePoints()
    {
        $closePoints = ClosePoint::with('closedBy')
            ->orderBy('close_date', 'desc')
            ->get();

        return response()->json([
            'data' => $closePoints
        ]);
    }

    /**
     * Get report between close points
     */
    public function closePointReport($closePointId, Request $request)
    {
        $closePoint = ClosePoint::findOrFail($closePointId);
        $previousClosePoint = $closePoint->previous();

        // Get totals for the period
        $query = Transaction::query();

        // Apply date range with exact datetime
        if ($previousClosePoint) {
            $query->where('created_at', '>=', $previousClosePoint->close_date);
        } else {
            $query->where('created_at', '>=', now()->startOfYear());
        }

        $query->where('created_at', '<=', $closePoint->close_date);

        // Apply type filter if provided
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Apply payment method filter if provided
        if ($request->has('payment_method_id') && $request->payment_method_id) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Get totals grouped by payment method
        $groupedTotals = (clone $query)
            ->selectRaw('payment_method_id,
            SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
            SUM(CASE
                WHEN type = "deposit" THEN total_amount
                WHEN type = "withdraw" THEN -total_amount
                WHEN type = "refund" THEN -total_amount
            END) as net_amount,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount_raw,
            SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount_raw,
            SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount_raw,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_deposit_amount,
            SUM(commission) as total_commission,
            SUM(shipping) as total_shipping')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        // Get overall totals
        $overallTotals = (clone $query)
            ->selectRaw('
            SUM(CASE WHEN type = "deposit" THEN total_amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN type = "withdraw" THEN total_amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN type = "refund" THEN total_amount ELSE 0 END) as total_refunds,
            SUM(CASE
                WHEN type = "deposit" THEN total_amount
                WHEN type = "withdraw" THEN -total_amount
                WHEN type = "refund" THEN -total_amount
            END) as net_amount,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as deposit_amount_raw,
            SUM(CASE WHEN type = "withdraw" THEN amount ELSE 0 END) as withdraw_amount_raw,
            SUM(CASE WHEN type = "refund" THEN amount ELSE 0 END) as refund_amount_raw,
            SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) as total_deposit_amount,
            SUM(commission) as total_commission,
            SUM(shipping) as total_shipping')
            ->first();

        return response()->json([
            'close_point' => $closePoint,
            'previous_close_point' => $previousClosePoint,
            'overall' => $overallTotals ?? (object)[
                    'total_deposits' => 0,
                    'total_withdrawals' => 0,
                    'total_refunds' => 0,
                    'net_amount' => 0,
                    'deposit_amount_raw' => 0,
                    'withdraw_amount_raw' => 0,
                    'refund_amount_raw' => 0,
                    'total_deposit_amount' => 0,
                    'total_commission' => 0,
                    'total_shipping' => 0
                ],
            'grouped_by_payment_method' => $groupedTotals
        ]);
    }

    /**
     * Get transactions for close point period
     */
    public function closePointTransactions($closePointId, Request $request)
    {
        $closePoint = ClosePoint::findOrFail($closePointId);
        $previousClosePoint = $closePoint->previous();

        if (!$previousClosePoint) {
            $startDate = now()->startOfYear()->format('Y-m-d');
        } else {
            $startDate = $previousClosePoint->close_date->format('Y-m-d');
        }

        $endDate = $closePoint->close_date->format('Y-m-d');

        $query = Transaction::query()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate . ' 23:59:59');

        // Apply additional filters
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('payment_method_id') && $request->payment_method_id) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Apply search if any
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('order_id', 'like', "%{$search}%");
            });
        }

        // Apply ordering
        $order = json_decode($request->order, true) ?? ['column' => 'id', 'dir' => 'desc'];
        $query->orderBy($order['column'], $order['dir']);

        // Pagination
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 10;
        $offset = ($page - 1) * $limit;

        $total = $query->count();
        $transactions = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'items' => TransactionResource::collection($transactions),
            'total' => $total
        ]);
    }

}
