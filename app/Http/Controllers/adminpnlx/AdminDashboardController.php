<?php

namespace App\Http\Controllers\adminpnlx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use DB;
use Illuminate\Validation\Rules\Password;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AdminDashboardController extends Controller
{
    public $model = 'dashboard';
    public function __construct(Request $request)
    {
        parent::__construct();
        View()->share('model', $this->model);
        $this->request = $request;
    }

    public function showdashboard(Request $request)
    {
        $totalOrders = Order::count();
        $totalTransactions = Transaction::count();
        $totalEnquiries = Enquiry::count();
        $totalUsers = User::where('is_active', 1)->where('is_deleted', 0)->count();
        $totalProducts = Product::where('is_active', 1)->count();

        $searchVariable = [];
        $searchVariable['UserData'] = $request->input('UserData', 'year');
        $UserData = $searchVariable['UserData'];

        $allUsers = [
            'x' => [],
            'y' => [],
        ];

        $allProducts = [
            'x' => [],
            'y' => [],
        ];

        if ($UserData == 'week') {
            for ($i = 0; $i < 7; $i++) {
                $day = date('Y-m-d', strtotime("-$i days"));

                // User count
                $userCount = DB::table('users')
                    ->where('created_at', '>=', $day . ' 00:00:00')
                    ->where('created_at', '<=', $day . ' 23:59:59')
                    ->count();
                $allUsers['x'][] = $userCount;
                $allUsers['y'][] = date('D', strtotime($day));

                // Product count
                $productCount = DB::table('products')
                    ->where('created_at', '>=', $day . ' 00:00:00')
                    ->where('created_at', '<=', $day . ' 23:59:59')
                    ->where('is_active', 1)
                    ->count();
                $allProducts['x'][] = $productCount;
                $allProducts['y'][] = date('D', strtotime($day));
            }
        } elseif ($UserData == 'month') {
            for ($i = 0; $i < 31; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));

                // User count
                $userCount = DB::table('users')
                    ->where('created_at', '>=', $date . ' 00:00:00')
                    ->where('created_at', '<=', $date . ' 23:59:59')
                    ->count();
                $allUsers['x'][] = $userCount;
                $allUsers['y'][] = date('d', strtotime($date));

                // Product count
                $productCount = DB::table('products')
                    ->where('created_at', '>=', $date . ' 00:00:00')
                    ->where('created_at', '<=', $date . ' 23:59:59')
                    ->where('is_active', 1)
                    ->count();
                $allProducts['x'][] = $productCount;
                $allProducts['y'][] = date('d', strtotime($date));
            }
        } elseif ($UserData == 'year') {
            for ($i = 0; $i < 12; $i++) {
                $month = date('Y-m', strtotime("-$i months"));

                // User count
                $userCount = DB::table('users')
                    ->where('created_at', '>=', $month . '-01 00:00:00')
                    ->where('created_at', '<=', $month . '-' . date('t', strtotime($month)) . ' 23:59:59')
                    ->count();
                $allUsers['x'][] = $userCount;
                $allUsers['y'][] = date('M', strtotime($month));

                // Product count
                $productCount = DB::table('products')
                    ->where('created_at', '>=', $month . '-01 00:00:00')
                    ->where('created_at', '<=', $month . '-' . date('t', strtotime($month)) . ' 23:59:59')
                    ->where('is_active', 1)
                    ->count();
                $allProducts['x'][] = $productCount;
                $allProducts['y'][] = date('M', strtotime($month));
            }
        } elseif ($UserData == '5y') {
            for ($i = 0; $i < 5; $i++) {
                $year = date('Y', strtotime("-$i years"));

                // User count
                $userCount = DB::table('users')
                    ->where('created_at', '>=', $year . '-01-01 00:00:00')
                    ->where('created_at', '<=', $year . '-12-31 23:59:59')
                    ->count();
                $allUsers['x'][] = $userCount;
                $allUsers['y'][] = $year;

                // Product count
                $productCount = DB::table('products')
                    ->where('created_at', '>=', $year . '-01-01 00:00:00')
                    ->where('created_at', '<=', $year . '-12-31 23:59:59')
                    ->where('is_active', 1)
                    ->count();
                $allProducts['x'][] = $productCount;
                $allProducts['y'][] = $year;
            }
        }

        $currentYear = Carbon::now()->year;
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();

        // Get user counts per month
        $userCounts = User::selectRaw('COUNT(*) as user_count, MONTH(created_at) as month')
            ->where('is_deleted', 0)
            ->where('is_active', 1)
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->groupBy('month')
            ->pluck('user_count', 'month')
            ->all();

        $months = range(1, 12);
        $userCounts = array_replace(array_fill_keys($months, 0), $userCounts);
        $registeredUsersData = array_values($userCounts);

        // Get product counts per month
        $productCounts = Product::selectRaw('COUNT(*) as product_count, MONTH(created_at) as month')
            ->where('is_active', 1)
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->groupBy('month')
            ->pluck('product_count', 'month')
            ->all();

        $productCounts = array_replace(array_fill_keys($months, 0), $productCounts);
        $productsData = array_values($productCounts);

        $shortMonths = [];
        foreach ($months as $month) {
            $shortMonths[] = Carbon::create($currentYear, $month, 1)->shortMonthName;
        }
        $shortMonthsJson = json_encode($shortMonths);
        $registeredUsersJson = json_encode($registeredUsersData);
        $productsJson = json_encode($productsData);

        return view('admin.dashboard.dashboard', compact('allUsers', 'allProducts', 'totalOrders', 'totalTransactions', 'totalEnquiries', 'totalProducts', 'shortMonthsJson', 'registeredUsersJson', 'productsJson', 'totalUsers'));
    }

    public function statistics(Request $request)
    {
        // dd("gg");
        $currentDate = Carbon::now();

        if ($request->lead_graph_type == 'weekly') {
            // Weekly setup
            $period = CarbonPeriod::create(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
            $weekDates = [];

            foreach ($period as $date) {
                $weekDates[] = $date->format('d M');
            }

            $dates = $weekDates;

            // Weekly user counts
            $currentWeekUserCounts = DB::table('users')
                ->selectRaw('COUNT(*) as user_count, DATE(created_at) as date')
                ->where('is_deleted', 0)
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('user_count', 'date')
                ->all();

            // Weekly product counts
            $currentWeekProductCounts = DB::table('products')
                ->selectRaw('COUNT(*) as product_count, DATE(created_at) as date')
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('product_count', 'date')
                ->all();

            // Format weekly data
            $registered_users_arr = array_fill(1, 7, 0);
            $products_arr = array_fill(1, 7, 0);

            foreach ($currentWeekUserCounts as $date => $count) {
                $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
                $registered_users_arr[$dayOfWeek] = $count;
            }

            foreach ($currentWeekProductCounts as $date => $count) {
                $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
                $products_arr[$dayOfWeek] = $count;
            }
        } elseif ($request->lead_graph_type == 'monthly') {
            // Monthly setup
            $period = CarbonPeriod::create(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
            $monthDates = [];

            foreach ($period as $date) {
                $monthDates[] = $date->format('d M');
            }

            $dates = $monthDates;

            // Monthly user counts
            $currentMonthUserCounts = DB::table('users')
                ->selectRaw('COUNT(*) as user_count, DAY(created_at) as day')
                ->where('is_deleted', 0)
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('user_count', 'day')
                ->all();

            // Monthly product counts
            $currentMonthProductCounts = DB::table('products')
                ->selectRaw('COUNT(*) as product_count, DAY(created_at) as day')
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('product_count', 'day')
                ->all();

            // Format monthly data
            $daysInMonth = Carbon::now()->daysInMonth;
            $registered_users_arr = array_fill(1, $daysInMonth, 0);
            $products_arr = array_fill(1, $daysInMonth, 0);

            foreach ($currentMonthUserCounts as $day => $count) {
                $registered_users_arr[$day] = $count;
            }

            foreach ($currentMonthProductCounts as $day => $count) {
                $products_arr[$day] = $count;
            }
        } elseif ($request->lead_graph_type == 'yearly') {
            // Yearly setup
            $dates = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            // Yearly user counts
            $currentYearUserCounts = DB::table('users')
                ->selectRaw('COUNT(*) as user_count, MONTH(created_at) as month')
                ->where('is_deleted', 0)
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('user_count', 'month')
                ->all();

            // Yearly product counts
            $currentYearProductCounts = DB::table('products')
                ->selectRaw('COUNT(*) as product_count, MONTH(created_at) as month')
                ->where('is_active', 1)
                ->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('product_count', 'month')
                ->all();

            // Format yearly data
            $registered_users_arr = array_fill_keys(range(1, 12), 0);
            $products_arr = array_fill_keys(range(1, 12), 0);

            foreach ($currentYearUserCounts as $month => $count) {
                $registered_users_arr[$month] = $count;
            }

            foreach ($currentYearProductCounts as $month => $count) {
                $products_arr[$month] = $count;
            }
        } elseif ($request->lead_graph_type == 'custom') {
            $date_from = $request->date_from;
            $date_to = $request->date_to;

            $registered_users_arr = [];
            $products_arr = [];
            $final_registered_users_arr = [];
            $final_products_arr = [];
            $new_date_from = Carbon::parse($date_from);
            $new_date_to = Carbon::parse($date_to);
            $dates = [];
            $currentDate = $new_date_from->copy();
            while ($currentDate->lte($new_date_to)) {
                $dates[] = $currentDate->format('d-m-Y');
                $currentDate->addDay();
            }

            $currentMonthCounts = DB::table('users')
                ->where('is_deleted', 0)
                ->where('is_active', 1)
                ->whereBetween('created_at', [$new_date_from, $new_date_to])
                ->selectRaw('COUNT(*) as user_count, DAY(created_at) as day_of_month')
                ->groupBy(DB::raw('DAY(created_at)'))
                ->orderBy('day_of_month')
                ->pluck('user_count', 'day_of_month')
                ->all();

            $registered_users_arr = array_fill(1, count($dates), 0);
            foreach ($currentMonthCounts as $day_of_month => $count) {
                $registered_users_arr[$day_of_month] = $count;
            }

            $currentProductCounts = DB::table('products')
                ->where('is_active', 1)
                ->whereBetween('created_at', [$new_date_from, $new_date_to])
                ->selectRaw('COUNT(*) as product_count, DAY(created_at) as day_of_month')
                ->groupBy(DB::raw('DAY(created_at)'))
                ->orderBy('day_of_month')
                ->pluck('product_count', 'day_of_month')
                ->all();

            $products_arr = array_fill(1, count($dates), 0);
            foreach ($currentProductCounts as $day_of_month => $count) {
                $products_arr[$day_of_month] = $count;
            }

            foreach ($dates as $date) {
                $day = Carbon::createFromFormat('d-m-Y', $date)->format('j');
                $final_registered_users_arr[] = $registered_users_arr[$day] ?? 0;
                $final_products_arr[] = $products_arr[$day] ?? 0;
            }
            

            $registered_users = json_encode($final_registered_users_arr);
            $products = json_encode($final_products_arr);

            $checkin_users = json_encode([]);
            $newArrayPercentage = json_encode(array_fill(0, count($final_registered_users_arr), 0));
            // dd($registered_users);
        }

        return response()->json([
            'registered_users' => json_encode(array_values($registered_users_arr)),
            'products' => json_encode(array_values($products_arr)),
            'dates' => $dates,
        ]);
    }

    public function myaccount(Request $request)
    {
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'name' => 'required',
                'email' => 'required|email|regex:/(.+)@(.+)\.(.+)/i',
            ]);
            $user = Admin::find(Auth::guard('admin')->user()->id);
            $user->name = $request->name;
            $user->email = $request->email;
            if ($user->save()) {
                return Redirect()->route('dashboard')->with('success', 'Information updated successfully.');
            }
        }
        $userInfo = Auth::guard('admin')->user();
        return View("admin.$this->model.myaccount", compact('userInfo'));
    }

    public function changedPassword(Request $request)
    {
        if ($request->isMethod('POST')) {
            $validated = $request->validate([
                'old_password' => 'required',
                'new_password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'confirm_password' => 'required|same:new_password',
            ]);
            $user = Admin::find(Auth::guard('admin')->user()->id);
            $oldpassword = $request->old_password;
            if (Hash::check($oldpassword, $user->getAuthPassword())) {
                $user->password = Hash::make($request->new_password);
                $user->save();
                return Redirect()->route('dashboard')->with('success', 'Password changed successfully.');
            } else {
                return Redirect()->route('dashboard')->with('error', 'Your old password is incorrect.');
            }
        }
        return View("admin.$this->model.changedPassword");
    }

    public function showVideo(Request $request)
    {
        $guid = $request->guid;
        return view('common.show_video', compact('guid'));
    }
}
