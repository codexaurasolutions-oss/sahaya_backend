<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Razorpay\Api\Api;
use App\Models\SubscriptionUser;
use App\Models\Subscription;

class GenerateRecurringOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:generate-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Razorpay orders for recurring subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );

        $subscriptions = SubscriptionUser::where('status', 'active')
            ->whereDate('end_date', '<=', now())
            ->where('status', 'active')
            ->get();
        
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::find($sub->subscription_id);
                if (!$subscription) {
                    $this->error("Subscription not found for User ID: " . $sub->user_id);
                    continue;
                }
                // Create Razorpay Order
                $order = $api->order->create([
                    "amount" => (int) $sub->amount * 100, // in paise
                    "currency" => "INR",
                    "receipt" => "renew_" . uniqid(),
                    "payment_capture" => 1
                ]);

                $subscriptionUser = SubscriptionUser::create([
                    'user_id' => $sub->user_id,
                    'subscription_id' => $sub->subscription_id,
                    'order_id' => $order['id'],
                    'order_number' => 'SUB' . time() . $sub->user_id,
                    'amount' => $sub->amount,
                    'currency' => 'INR',
                    'payment_status' => 'paid',
                    'role' => $sub->role,
                    'type' => 'credit',
                    'start_date' => now(),
                    'end_date' => now()->addDays($subscription->validity),
                ]);

                $sub->update([
                    'status' => 'inactive',
                ]);

                $this->info("Order created for User ID: " . $sub->user_id);

            } catch (\Exception $e) {

                $this->error("Failed for User ID: " . $sub->user_id);
                \Log::error($e->getMessage());
            }
        }
        return Command::SUCCESS;
    }
}
