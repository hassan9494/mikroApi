<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Admin\Http\Resources\CouponReportResource;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Traits\Datatable;

class CouponReportController extends Controller
{
    use ApiResponser;

    /**
     * Get coupon report list
     */
    public function index(): JsonResponse
    {
        $conditions = json_decode(request('conditions', '[]'), true);
        $search = request('search', '');
        $order = json_decode(request('order', '{}'), true);
        $page = request('page', 0);
        $limit = request('limit', 10);

        $query = Coupon::query();

        // Apply search
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                    ->orWhere('code', 'LIKE', "%$search%")
                    ->orWhere('amount', 'LIKE', "%$search%");
            });
        }

        // Apply conditions from frontend
        foreach ($conditions as $condition) {
            if (isset($condition['col']) && isset($condition['op']) && isset($condition['val'])) {
                $column = $condition['col'];
                $operator = $condition['op'];
                $value = $condition['val'];

                if ($column === 'status') {
                    // Handle calculated status
                    $now = Carbon::now();
                    switch($value) {
                        case 'Active':
                            $query->where('active', true)
                                ->where(function($q) use ($now) {
                                    $q->whereNull('start_at')
                                        ->orWhere('start_at', '<=', $now);
                                })
                                ->where(function($q) use ($now) {
                                    $q->whereNull('end_at')
                                        ->orWhere('end_at', '>=', $now);
                                })
                                ->where(function($q) {
                                    $q->where('count', '<=', 0)
                                        ->orWhereRaw('use_count < count');
                                });
                            break;
                        case 'Inactive':
                            $query->where('active', false);
                            break;
                        case 'Expired':
                            $query->where(function($q) use ($now) {
                                $q->where('end_at', '<', $now)
                                    ->orWhere(function($q2) {
                                        $q2->where('count', '>', 0)
                                            ->whereRaw('use_count >= count');
                                    });
                            });
                            break;
                        case 'Scheduled':
                            $query->where('start_at', '>', $now);
                            break;
                        case 'Fully Used':
                            $query->where('count', '>', 0)
                                ->whereRaw('use_count >= count');
                            break;
                    }
                } else {
                    // Regular column conditions
                    if ($operator === 'IN') {
                        $query->whereIn($column, $value);
                    } else {
                        $query->where($column, $operator, $value);
                    }
                }
            }
        }

        // Count total
        $total = $query->count();

        // Apply ordering
        if (!empty($order) && isset($order['column']) && isset($order['dir'])) {
            $query->orderBy($order['column'], $order['dir']);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Apply pagination
        $items = $query->skip($page * $limit)->take($limit)->get();

        // Calculate additional metrics for each coupon
        $items->each(function($coupon) {
            // Load orders count for this coupon
            $coupon->orders_count = $coupon->orders()->count();

            // Calculate total discount given
            $coupon->total_discount = $coupon->orders()->sum('discount');

            // Calculate redemption rate
            $coupon->redemption_rate = $coupon->count > 0
                ? ($coupon->use_count / $coupon->count) * 100
                : 0;
        });

        return response()->json([
            'data' => [
                'items' => CouponReportResource::collection($items),
                'total' => $total
            ]
        ]);
    }

    /**
     * Get detailed coupon report with orders
     */
    public function show($id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        // Get filter parameters
        $status = request('status', 'all');
        $from = request('from');
        $to = request('to');

        // Build orders query
        $ordersQuery = $coupon->orders();

        // Apply status filter
        if ($status && $status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        // Apply date filters
        if ($from) {
            $ordersQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $ordersQuery->whereDate('created_at', '<=', $to);
        }

        // Get orders with sorting
        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();

        // Calculate statistics based on filtered orders
        $statistics = [
            'total_orders' => $orders->count(),
            'total_discount' => $orders->sum('discount'),
            'total_orders_value' => $orders->sum('subtotal'),
            'average_discount_per_order' => $orders->count() > 0
                ? $orders->sum('discount') / $orders->count()
                : 0,
            'average_order_value' => $orders->count() > 0
                ? $orders->sum('subtotal') / $orders->count()
                : 0,
            'first_used' => $orders->min('created_at'),
            'last_used' => $orders->max('created_at'),
            'remaining_uses' => max(0, $coupon->count - $coupon->use_count),
            'usage_percentage' => $coupon->count > 0
                ? ($coupon->use_count / $coupon->count) * 100
                : 0,
        ];

        // Format orders
        $formattedOrders = $orders->map(function($order) {
            return [
                'id' => $order->id,
                'number' => $order->number,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'customer' => $order->customer,
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'total' => $order->total,
                'status' => $order->status,
                'discount_percentage' => $order->subtotal > 0
                    ? ($order->discount / $order->subtotal) * 100
                    : 0,
                'url' => url('/order/edit/' . $order->id),
            ];
        });

        return response()->json([
            'coupon' => new CouponReportResource($coupon),
            'statistics' => $statistics,
            'orders' => $formattedOrders
        ]);
    }

    /**
     * Get coupon usage statistics by time period
     */
    public function usageStatistics($id): JsonResponse
    {
        $period = request('period', 'daily'); // daily, weekly, monthly, yearly
        $days = request('days', 30);

        $coupon = Coupon::findOrFail($id);

        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($days);

        $query = Order::where('coupon_id', $id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(subtotal) as total_value'),
                DB::raw('SUM(discount) as total_discount')
            )
            ->groupBy('date')
            ->orderBy('date');

        $statistics = $query->get();

        // Fill in missing dates
        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $stat = $statistics->firstWhere('date', $dateStr);

            $result[] = [
                'date' => $dateStr,
                'order_count' => $stat ? $stat->order_count : 0,
                'total_value' => $stat ? $stat->total_value : 0,
                'total_discount' => $stat ? $stat->total_discount : 0,
                'average_discount' => $stat && $stat->order_count > 0
                    ? $stat->total_discount / $stat->order_count
                    : 0,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'period' => $period,
            'days' => $days,
            'statistics' => $result,
            'summary' => [
                'total_orders' => $statistics->sum('order_count'),
                'total_value' => $statistics->sum('total_value'),
                'total_discount' => $statistics->sum('total_discount'),
                'average_daily_orders' => $days > 0 ? $statistics->sum('order_count') / $days : 0,
                'average_daily_discount' => $days > 0 ? $statistics->sum('total_discount') / $days : 0,
            ]
        ]);
    }

    /**
     * Export coupon report to Excel
     */
    public function export($id = null): JsonResponse
    {
        if ($id) {
            // Export single coupon details
            $coupon = Coupon::with(['orders'])->findOrFail($id);
            $data = $this->prepareCouponExportData($coupon);
        } else {
            // Export all coupons
            $coupons = Coupon::withCount(['orders'])->get();
            $data = $this->prepareAllCouponsExportData($coupons);
        }

        return response()->json([
            'data' => $data,
            'filename' => $id ? "coupon-report-{$coupon->code}.xlsx" : "coupons-report-all.xlsx"
        ]);
    }

    private function prepareCouponExportData($coupon)
    {
        return [
            'coupon_info' => [
                ['Field', 'Value'],
                ['Coupon ID', $coupon->id],
                ['Name', $coupon->name],
                ['Code', $coupon->code],
                ['Amount', $coupon->amount . ($coupon->is_percentage ? '%' : '')],
                ['Type', $coupon->is_percentage ? 'Percentage' : 'Fixed Amount'],
                ['Start Date', $coupon->start_at?->format('Y-m-d')],
                ['End Date', $coupon->end_at?->format('Y-m-d')],
                ['Usage Limit', $coupon->count],
                ['Used Count', $coupon->use_count],
                ['Remaining', max(0, $coupon->count - $coupon->use_count)],
                ['Active', $coupon->active ? 'Yes' : 'No'],
                ['Valid', $coupon->valid ? 'Yes' : 'No'],
            ],
            'orders' => [
        ['Order ID', 'Date', 'Customer', 'Subtotal', 'Discount', 'Total', 'Status'],
        ...$coupon->orders->map(function($order) {
            return [
                $order->number,
                $order->created_at->format('Y-m-d H:i'),
                $order->customer->name ?? 'N/A',
                $order->subtotal,
                $order->discount,
                $order->total,
                $order->status,
            ];
        })->toArray()
    ],
            'statistics' => [
        ['Metric', 'Value'],
        ['Total Orders', $coupon->orders->count()],
        ['Total Discount Given', $coupon->orders->sum('discount')],
        ['Total Orders Value', $coupon->orders->sum('subtotal')],
        ['Average Discount per Order', $coupon->orders->count() > 0 ?
            $coupon->orders->sum('discount') / $coupon->orders->count() : 0],
        ['Redemption Rate', $coupon->count > 0 ?
            ($coupon->use_count / $coupon->count) * 100 : 0],
    ]
        ];
    }

    private function prepareAllCouponsExportData($coupons)
    {
        return [
            'coupons' => [
                ['ID', 'Name', 'Code', 'Type', 'Amount', 'Usage', 'Total Discount', 'Orders', 'Status', 'Valid Until'],
                ...$coupons->map(function($coupon) {
                    return [
                        $coupon->id,
                        $coupon->name,
                        $coupon->code,
                        $coupon->is_percentage ? 'Percentage' : 'Fixed',
                        $coupon->amount . ($coupon->is_percentage ? '%' : ''),
                        $coupon->use_count . '/' . $coupon->count,
                        $coupon->orders->sum('discount') ?? 0,
                        $coupon->orders_count,
                        $coupon->active ? 'Active' : 'Inactive',
                        $coupon->end_at?->format('Y-m-d') ?? 'N/A',
                    ];
                })->toArray()
            ]
        ];
    }

    public function exportAll(Request $request)
    {
        $conditions = json_decode($request->get('conditions', '[]'), true);

        // Build query same as index method
        $query = Coupon::query();

        $type = null;
        $from = null;
        $to = null;

        foreach ($conditions as $condition) {
            if (isset($condition['col']) && $condition['col'] === 'is_percentage' && isset($condition['val'])) {
                $type = $condition['val'];
            } elseif (isset($condition['col']) && $condition['col'] === 'from' && isset($condition['val'])) {
                $from = $condition['val'];
            } elseif (isset($condition['col']) && $condition['col'] === 'to' && isset($condition['val'])) {
                $to = $condition['val'];
            }
        }

        if ($type !== null) {
            $query->where('is_percentage', $type);
        }

        $coupons = $query->get();

        // Create CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="coupons_export_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($coupons, $from, $to) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'ID', 'Name', 'Code', 'Type', 'Amount',
                'Start Date', 'End Date', 'Usage', 'Total Discount',
                'Total Orders', 'Active', 'Status'
            ]);

            // Add data rows
            foreach ($coupons as $coupon) {
                // Load orders with date filters
                $coupon->load(['orders' => function($query) use ($from, $to) {
                    if ($from) $query->whereDate('created_at', '>=', $from);
                    if ($to) $query->whereDate('created_at', '<=', $to);
                }]);

                fputcsv($file, [
                    $coupon->id,
                    $coupon->name,
                    $coupon->code,
                    $coupon->is_percentage ? 'Percentage' : 'Fixed',
                    $coupon->amount . ($coupon->is_percentage ? '%' : ''),
                    $coupon->start_at ? $coupon->start_at->format('Y-m-d') : '',
                    $coupon->end_at ? $coupon->end_at->format('Y-m-d') : '',
                    $coupon->use_count . '/' . $coupon->count,
                    $coupon->orders->sum('discount'),
                    $coupon->orders->count(),
                    $coupon->active ? 'Yes' : 'No',
                    $coupon->status
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportSingle($id, Request $request)
    {
        $coupon = Coupon::findOrFail($id);

        // Get filter parameters
        $status = $request->get('status', 'all');
        $from = $request->get('from');
        $to = $request->get('to');

        // Build query same as show method
        $ordersQuery = $coupon->orders();

        if ($status && $status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        if ($from) {
            $ordersQuery->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $ordersQuery->whereDate('created_at', '<=', $to);
        }

        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="coupon_' . $coupon->code . '_orders_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($coupon, $orders) {
            $file = fopen('php://output', 'w');

            // Coupon information
            fputcsv($file, ['Coupon Information']);
            fputcsv($file, ['ID', $coupon->id]);
            fputcsv($file, ['Name', $coupon->name]);
            fputcsv($file, ['Code', $coupon->code]);
            fputcsv($file, ['Type', $coupon->is_percentage ? 'Percentage' : 'Fixed']);
            fputcsv($file, ['Amount', $coupon->amount . ($coupon->is_percentage ? '%' : '')]);
            fputcsv($file, ['Start Date', $coupon->start_at ? $coupon->start_at->format('Y-m-d') : '']);
            fputcsv($file, ['End Date', $coupon->end_at ? $coupon->end_at->format('Y-m-d') : '']);
            fputcsv($file, ['Usage', $coupon->use_count . '/' . $coupon->count]);
            fputcsv($file, ['Active', $coupon->active ? 'Yes' : 'No']);
            fputcsv($file, []);

            // Orders header
            fputcsv($file, ['Order ID', 'Date', 'Customer', 'Phone', 'Subtotal', 'Discount', 'Total', 'Status']);

            // Orders data
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->number,
                    $order->created_at->format('Y-m-d H:i'),
                    $order->customer->name ?? 'N/A',
                    $order->customer->phone ?? 'N/A',
                    $order->subtotal,
                    $order->discount,
                    $order->total,
                    $order->status
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
