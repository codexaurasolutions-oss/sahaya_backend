<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
     public function customerAnalytics()
    {
        // Total Registered Customers (assuming role_id = 2 for customers)
        $totalCustomers = User::where('user_role_id', 2)->count();
        
        // Customers with at least 1 Booking
        $customersWithBookings = User::where('user_role_id', 2)
            ->whereHas('bookingsAsCustomer')
            ->count();
        
        // Average Appointments per Customer
        $bookingsStats = Booking::whereNotNull('customer_id')
            ->select(DB::raw('customer_id, COUNT(*) as booking_count'))
            ->groupBy('customer_id')
            ->get();
        
        $avgAppointments = $bookingsStats->avg('booking_count') ?? 0;
        
        // Total Revenue from Completed Bookings
        $totalRevenue = Booking::where('status', 'completed')
            ->sum(DB::raw('amount + tax + platform_fee'));
        
        // Average Customer LTV (Lifetime Value)
        $avgLTV = $customersWithBookings > 0 ? $totalRevenue / $customersWithBookings : 0;
        
        // Appointment Status Breakdown
        $appointmentStatus = Booking::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        
        $totalAppointments = $appointmentStatus->sum();
        
        // Calculate percentages
        $completedPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['completed'] ?? 0) / $totalAppointments * 100 : 0;
        $pendingPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['pending'] ?? 0) / $totalAppointments * 100 : 0;
        $cancelledPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['cancelled'] ?? 0) / $totalAppointments * 100 : 0;
        
        // Monthly Revenue Growth (MoM)
        $currentMonthRevenue = Booking::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum(DB::raw('amount + tax + platform_fee'));
            
        $lastMonthRevenue = Booking::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum(DB::raw('amount + tax + platform_fee'));
        
        $revenueGrowth = $lastMonthRevenue > 0 ? 
            (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;
        
        // Cancellation Charges
        $cancellationCharges = Booking::where('status', 'cancelled')
            ->sum('platform_fee');
        
        // Top Customers by Spending
        $topCustomers = User::where('user_role_id', 2)
            ->with(['bookingsAsCustomer' => function($query) {
                $query->where('status', 'completed');
            }])
            ->get()
            ->map(function($customer) {
                $totalSpent = $customer->bookingsAsCustomer->sum(function($booking) {
                    return $booking->amount + $booking->tax + $booking->platform_fee;
                });
                
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'total_bookings' => $customer->bookingsAsCustomer->count(),
                    'total_spent' => round($totalSpent, 2),
                    'last_booking' => $customer->bookingsAsCustomer->max('created_at')
                ];
            })
            ->sortByDesc('total_spent')
            ->take(10)
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'customer_overview' => [
                    'total_registered_customers' => $totalCustomers,
                    'customers_with_at_least_1_booking' => $customersWithBookings,
                    'average_appointments_per_customer' => round($avgAppointments, 1),
                    'average_customer_ltv' => round($avgLTV, 2),
                    'total_revenue_from_customers' => round($totalRevenue, 2)
                ],
                'revenue_metrics' => [
                    'average_revenue_per_customer_mom' => round($currentMonthRevenue / max($totalCustomers, 1), 2),
                    'revenue_growth_percentage' => round($revenueGrowth, 2),
                    'total_cancellation_charges_generated' => round($cancellationCharges, 2)
                ],
                'appointment_status' => [
                    'completed' => round($completedPercentage, 1),
                    'pending' => round($pendingPercentage, 1),
                    'cancelled' => round($cancelledPercentage, 1),
                    'total_appointments' => $totalAppointments
                ],
                'top_customers' => $topCustomers,
                'summary' => [
                    'active_customers_rate' => $totalCustomers > 0 ? 
                        round(($customersWithBookings / $totalCustomers) * 100, 2) : 0,
                    'average_booking_value' => $totalAppointments > 0 ? 
                        round($totalRevenue / $totalAppointments, 2) : 0,
                    'cancellation_rate' => round($cancelledPercentage, 2)
                ]
            ]
        ]);
    }
     public function vendorAnalytics()
    {
        // Total Providers Listed (assuming role_id = 3 for vendors)
        $totalProviders = User::where('user_role_id', 3)->count();
        
        // Profile Completion Status
        $profileCompleted = User::where('user_role_id', 3)
            ->where('step', 'completed')
            ->count();
        
        $profileInReview = $totalProviders - $profileCompleted;
        
        // Revenue Metrics
        $currentMonthRevenue = Booking::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum(DB::raw('amount + tax + platform_fee'));
            
        $lastMonthRevenue = Booking::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum(DB::raw('amount + tax + platform_fee'));
        
        $averageRevenuePerProvider = $totalProviders > 0 ? $currentMonthRevenue / $totalProviders : 0;
        $revenueGrowth = $lastMonthRevenue > 0 ? 
            (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;
        
        // Cancellation Charges
        $cancellationCharges = Booking::where('status', 'cancelled')
            ->sum('platform_fee');
        
        // Appointment Status Breakdown
        $appointmentStatus = Booking::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        
        $totalAppointments = $appointmentStatus->sum();
        $completedPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['completed'] ?? 0) / $totalAppointments * 100 : 0;
        $pendingPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['pending'] ?? 0) / $totalAppointments * 100 : 0;
        $cancelledPercentage = $totalAppointments > 0 ? 
            ($appointmentStatus['cancelled'] ?? 0) / $totalAppointments * 100 : 0;
        
        // Vendor Performance Metrics
        $vendorsPerformance = User::where('user_role_id', 3)
            ->withCount(['bookingsAsVendor as total_bookings'])
            ->with(['bookingsAsVendor'])
            ->get()
            ->map(function($vendor) {
                $completedBookings = $vendor->bookingsAsVendor->where('status', 'completed');
                $cancelledBookings = $vendor->bookingsAsVendor->where('status', 'cancelled');
                
                $totalRevenue = $completedBookings->sum(function($booking) {
                    return $booking->amount + $booking->tax + $booking->platform_fee;
                });
                
                $completionRate = $vendor->total_bookings > 0 ? 
                    ($completedBookings->count() / $vendor->total_bookings) * 100 : 0;
                
                $cancellationRate = $vendor->total_bookings > 0 ? 
                    ($cancelledBookings->count() / $vendor->total_bookings) * 100 : 0;
                
                return [
                    'id' => $vendor->id,
                    'business_name' => $vendor->business_name,
                    'category' => $vendor->category,
                    'total_bookings' => $vendor->total_bookings,
                    'completed_bookings' => $completedBookings->count(),
                    'cancelled_bookings' => $cancelledBookings->count(),
                    'completion_rate' => round($completionRate, 2),
                    'cancellation_rate' => round($cancellationRate, 2),
                    'total_revenue' => round($totalRevenue, 2),
                    'profile_status' => $vendor->steps === 'completed' ? 'Completed' : 'In Review',
                    'city' => $vendor->city,
                    'years_of_experience' => $vendor->years_of_experience
                ];
            });
        
        // Top Performing Vendors
        $topVendors = $vendorsPerformance->sortByDesc('total_revenue')->take(10)->values();
        
        // Category-wise Performance
        $categoryPerformance = $vendorsPerformance->groupBy('category')->map(function($vendors, $category) {
            return [
                'category' => $category ?: 'Uncategorized',
                'total_vendors' => $vendors->count(),
                'total_revenue' => round($vendors->sum('total_revenue'), 2),
                'average_completion_rate' => round($vendors->avg('completion_rate'), 2),
                'average_cancellation_rate' => round($vendors->avg('cancellation_rate'), 2)
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'provider_overview' => [
                    'total_providers_listed' => $totalProviders,
                    'profile_completed' => $profileCompleted,
                    'profile_in_review' => $profileInReview
                ],
                'revenue_metrics' => [
                    'average_revenue_per_provider_mom' => round($averageRevenuePerProvider, 2),
                    'revenue_growth_percentage' => round($revenueGrowth, 2),
                    'total_cancellation_charges_generated' => round($cancellationCharges, 2),
                    'current_month_revenue' => round($currentMonthRevenue, 2),
                    'last_month_revenue' => round($lastMonthRevenue, 2)
                ],
                'appointment_status' => [
                    'completed' => round($completedPercentage, 1),
                    'pending' => round($pendingPercentage, 1),
                    'cancelled' => round($cancelledPercentage, 1),
                    'total_appointments' => $totalAppointments
                ],
                'performance_metrics' => [
                    'average_completion_rate' => round($vendorsPerformance->avg('completion_rate'), 2),
                    'average_cancellation_rate' => round($vendorsPerformance->avg('cancellation_rate'), 2),
                    'total_platform_revenue' => round($vendorsPerformance->sum('total_revenue'), 2),
                    'average_bookings_per_vendor' => round($vendorsPerformance->avg('total_bookings'), 1)
                ],
                'top_performing_vendors' => $topVendors,
                'category_performance' => $categoryPerformance,
                'summary' => [
                    'active_vendors_rate' => round(($profileCompleted / $totalProviders) * 100, 2),
                    'average_revenue_per_booking' => $totalAppointments > 0 ? 
                        round($currentMonthRevenue / $totalAppointments, 2) : 0,
                    'platform_utilization_rate' => round(($totalAppointments / ($totalProviders * 30)) * 100, 2) // Assuming 30 days
                ]
            ]
        ]);
    }
    
}