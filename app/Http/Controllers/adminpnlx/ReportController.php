<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Models\OrderProducts;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use App\Exports\OrdersCommissionExport;
use App\Exports\PlacedMoreOrders;
use App\Exports\OrderRevenueExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public $model = 'reports';
    public function __construct(Request $request){
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function orderrevenueindex(Request $request)
    {
        $DB = Order::query();
        $searchVariable = [];
        $inputGet = $request->all();

        // $ordersCount = OrderProducts::query();
        $totalAdminRevenue = Order::sum('pay_amount');
        $totalorders = Order::count();
        
        $searchVariable = [];
        $searchVariable['Data'] = $request->input('Data', 'year');
        $Data = $searchVariable['Data'];

        $revenueData = [
            'x' => [],
            'y' => [],
        ];

        $orderCountData = [
            'x' => [],
            'y' => [],
        ];

        $currentDate = Carbon::now();

        if ($Data == 'week') {
            for ($i = 0; $i < 7; $i++) {
                $day = $currentDate->copy()->subDays($i)->format('Y-m-d');

                $totalRevenue = DB::table('orders')
                    ->whereDate('created_at', $day)
                    ->sum('pay_amount');

                $orderCount = DB::table('orders')
                    ->whereDate('created_at', $day)
                    ->count();

                $orderCountData['x'][] = $orderCount;
                $revenueData['x'][] = $totalRevenue;
                $revenueData['y'][] = date('D', strtotime($day));
            }
            $revenueData['x'] = array_reverse($revenueData['x']);
            $revenueData['y'] = array_reverse($revenueData['y']);
            $orderCountData['x'] = array_reverse($orderCountData['x']);
        } elseif ($Data == 'month') {
            $daysInMonth = $currentDate->daysInMonth;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($currentDate->year, $currentDate->month, $day);

                $totalRevenue = DB::table('orders')
                    ->whereDate('created_at', $date)
                    ->sum('pay_amount');

                $orderCount = DB::table('orders')
                    ->whereDate('created_at', $date)
                    ->count(); 

                $orderCountData['x'][] = $orderCount;
                $revenueData['x'][] = $totalRevenue;
                $revenueData['y'][] = $date->format('d');
            }
        } elseif ($Data == 'year') {
            for ($month = 1; $month <= 12; $month++) {
                $totalRevenue = DB::table('orders')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $currentDate->year)
                    ->sum('pay_amount');

                $orderCount = DB::table('orders')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $currentDate->year)
                    ->count(); 

                $orderCountData['x'][] = $orderCount;
                $revenueData['x'][] = $totalRevenue;
                $revenueData['y'][] = date('M', mktime(0, 0, 0, $month, 1));
            }
        }

        $shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $shortMonthsJson = json_encode($shortMonths);

        $orderRevenueJson = json_encode($revenueData['x']);
        $orderCountJson = json_encode($orderCountData['x']);

        if ($request->all()) {
            $searchData = $request->all();
            unset($searchData['display']);
            unset($searchData['_token']);
            unset($searchData['order']);
            unset($searchData['sortBy']);
            unset($searchData['page']);

            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != '') {
                    if ($fieldName == 'order_status' && $fieldValue != '') {
                        $DB->where('orders.status', $fieldValue);
                    }
                    if ($fieldName == 'start_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '>=', $fieldValue);
                    }

                    if ($fieldName == 'end_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '<=', $fieldValue);
                    }

                    $searchVariable[$fieldName] = $fieldValue;
                }
            }
        }

        $sortBy = $request->input('sortBy', 'created_at');
        $order = $request->input('order', 'DESC');
        $records_per_page = $request->input('per_page', Config('Reading.records_per_page'));
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string['sortBy'], $complete_string['order']);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet);

        return view("admin.$this->model.order-revenue-index", compact(
            'request',
            'revenueData',
            'shortMonthsJson',
            'orderRevenueJson',
            'orderCountJson',
            'totalAdminRevenue',
            'totalorders',
            'results',
            'searchVariable',
            'sortBy',
            'order',
            'query_string'
        ));
    }

    
    public function statistics(Request $request)
    {
        $currentDate = Carbon::now();
        $revenue_arr = [];
        $dates = [];

        if ($request->lead_graph_type == 'weekly') {
            // Weekly setup
            $period = CarbonPeriod::create(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
            $weekDates = [];
            $revenue_arr = [];
            $order_count_arr = []; 
            foreach ($period as $date) {
                $weekDates[] = $date->format('d M'); 
                $specificDate = $date->format('Y-m-d');
                
                $totalRevenue = DB::table('orders')
                    ->whereDate('created_at', $specificDate)
                    ->sum('pay_amount');
                $revenue_arr[] = $totalRevenue;

            }
            $dates = $weekDates;

        } elseif ($request->lead_graph_type == 'monthly') {
            // Monthly setup
            $daysInMonth = $currentDate->daysInMonth;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::createFromDate($currentDate->year, $currentDate->month, $day);
                $dates[] = $date->format('d M');
        
                $totalRevenue = DB::table('orders')
                    ->whereYear('created_at', $currentDate->year)
                    ->whereMonth('created_at', $currentDate->month)
                    ->whereDay('created_at', $day)
                    ->sum('pay_amount');
                $revenue_arr[] = $totalRevenue;

               
            }

        } elseif ($request->lead_graph_type == 'yearly') {
            // Yearly setup
            for ($month = 1; $month <= 12; $month++) {
                $dates[] = date('M', mktime(0, 0, 0, $month, 1));
                
                $totalRevenue = DB::table('orders')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $currentDate->year)
                    ->sum('pay_amount');
                $revenue_arr[] = $totalRevenue;

              
            }
        } elseif ($request->lead_graph_type == 'custom') {
            $date_from = Carbon::parse($request->date_from);
            $date_to = Carbon::parse($request->date_to);
            $period = CarbonPeriod::create($date_from, $date_to);

            foreach ($period as $date) {
                $dates[] = $date->format('d M');

                $totalRevenue = DB::table('orders')
                    ->whereDate('created_at', $date)
                    ->sum('pay_amount');
                $revenue_arr[] = $totalRevenue;

               
            }
        }

        return response()->json([
            'total_revenue' => json_encode(array_values($revenue_arr)),
            'dates' => $dates,
        ]);
    }

     public function orderstatistics(Request $request)
    {
        // dd(55);
        $currentDate = Carbon::now();
        $order_count_arr = []; 
        $dates = [];

        if ($request->order_lead_graph_type == 'weekly') {
            // Weekly setup
            $period = CarbonPeriod::create(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
            $weekDates = [];
            $order_count_arr = []; 
            foreach ($period as $date) {
                $weekDates[] = $date->format('d M'); 
                $specificDate = $date->format('Y-m-d');
                $orderCount = DB::table('orders')
                    ->whereDate('created_at', $specificDate)
                    ->count();
                $order_count_arr[] = $orderCount;
            }
            $dates = $weekDates;

        } elseif ($request->order_lead_graph_type == 'monthly') {
            // Monthly setup
            $daysInMonth = $currentDate->daysInMonth;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::createFromDate($currentDate->year, $currentDate->month, $day);
                $dates[] = $date->format('d M');
                $orderCount = DB::table('orders')
                    ->whereYear('created_at', $currentDate->year)
                    ->whereMonth('created_at', $currentDate->month)
                    ->whereDay('created_at', $day)
                    ->count();
                $order_count_arr[] = $orderCount;
            }

        } elseif ($request->order_lead_graph_type == 'yearly') {
            // Yearly setup
            for ($month = 1; $month <= 12; $month++) {
                $dates[] = date('M', mktime(0, 0, 0, $month, 1));
                $orderCount = DB::table('orders')
                    ->whereMonth('created_at', $month)
                    ->whereYear('created_at', $currentDate->year)
                    ->count();
                $order_count_arr[] = $orderCount;
            }
        } elseif ($request->order_lead_graph_type == 'custom') {
            $date_from = Carbon::parse($request->date_from);
            $date_to = Carbon::parse($request->date_to);
            $period = CarbonPeriod::create($date_from, $date_to);

            foreach ($period as $date) {
                $dates[] = $date->format('d M');
                $orderCount = DB::table('orders')
                    ->whereDate('created_at', $date)
                    ->count();
                $order_count_arr[] = $orderCount;
            }
        }

        return response()->json([
            'order_count' => json_encode(array_values($order_count_arr)),
            'dates' => $dates,
        ]);
    }

    public function exportorderrevenue(Request $request)
    {
        return Excel::download(new OrderRevenueExport($request->all()), 'Order_Revenue.xlsx');
    }
   
    public function placedmoreorders(Request $request) {
        $DB = Order::query();
        $DB->join('users', 'orders.user_id', '=', 'users.id');
        
        $totalorders = Order::select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
        
        $DB->leftJoin(
            DB::raw('(SELECT user_id, COUNT(*) as total FROM orders GROUP BY user_id) as total_orders'),
            'total_orders.user_id',
            '=',
            'users.id'
        );
        
        $userIds = Order::pluck('user_id')->unique();
        
        $searchVariable = [];
        $inputGet = $request->all();
        if ($request->all()) {
            $searchData = $request->all();
            unset($searchData['display']);
            unset($searchData['_token']);
            unset($searchData['order']);
            unset($searchData['sortBy']);
            unset($searchData['page']);
        
            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != '') {
                    if ($fieldName === 'email') {
                        $DB->where('users.email', 'LIKE', '%' . $fieldValue . '%');
                    }
                    if ($fieldName === 'phone_number') {
                        $DB->where('users.phone_number', 'LIKE', '%' . $fieldValue . '%');
                    }
                    if ($fieldName == 'start_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '>=', $fieldValue);
                    }
                    if ($fieldName == 'end_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '<=', $fieldValue);
                    }
        
                    $searchVariable[$fieldName] = $fieldValue;
                }
            }
        }
        
        // Set default sorting to total orders in descending order
        $sortBy = $request->input('sortBy', 'total_orders.total');
        $order = $request->input('order', 'DESC');
        $records_per_page = $request->input('per_page', Config('Reading.records_per_page'));
        
        $results = $DB->select('users.id as user_id', 'users.email', 'users.phone_number', 'total_orders.total')
            ->groupBy('users.id', 'users.email', 'users.phone_number', 'total_orders.total')
            ->orderBy($sortBy, $order) // Order by total orders
            ->paginate($records_per_page);
        
        $complete_string = $request->query();
        unset($complete_string['sortBy'], $complete_string['order']);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet);
        
        return view("admin.$this->model.moreordersplaced", compact('request', 'results', 'searchVariable', 'sortBy', 'order', 'query_string', 'totalorders'));
    }        

    public function exportPlacedMoreOrders(Request $request)
    {
        return Excel::download(new PlacedMoreOrders($request->all()), 'More_Order_placed.xlsx');
    }
    
    public function orders_commission(Request $request){
        $totalAdminCommission = Order::sum('admin_commission_amount');
        $DB = Order::query();
        $searchVariable = [];
        $inputGet = $request->all();

        if ($request->all()) {
            $searchData = $request->all();
            unset($searchData['display']);
            unset($searchData['_token']);
            unset($searchData['order']);
            unset($searchData['sortBy']);
            unset($searchData['page']);

            foreach ($searchData as $fieldName => $fieldValue) {
                if ($fieldValue != '') {
                   
                    if ($fieldName == 'order_number' && $fieldValue != '') {
                        $DB->where('orders.order_number', $fieldValue);
                    }
                    if ($fieldName == 'start_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '>=', $fieldValue);
                    }

                    if ($fieldName == 'end_date' && $fieldValue != '') {
                        $DB->whereDate('orders.created_at', '<=', $fieldValue);
                    }

                    $searchVariable[$fieldName] = $fieldValue;
                }
            }
        }

        $sortBy = $request->input('sortBy', 'created_at');
        $order = $request->input('order', 'DESC');
        $records_per_page = $request->input('per_page', Config('Reading.records_per_page'));
        $results = $DB->orderBy($sortBy, $order)->paginate($records_per_page);
        $complete_string = $request->query();
        unset($complete_string['sortBy'], $complete_string['order']);
        $query_string = http_build_query($complete_string);
        $results->appends($inputGet);
    
       return view("admin.$this->model.order-commission", compact('request','totalAdminCommission','results', 'searchVariable', 'sortBy', 'order', 'query_string'));
    }

    public function exportOrdersCommission(Request $request)
    {
        return Excel::download(new OrdersCommissionExport($request->all()), 'orders_commission.xlsx');
    }

    
}
