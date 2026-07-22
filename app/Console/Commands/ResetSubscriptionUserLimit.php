<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SubscriptionUser;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Subscription;


class ResetSubscriptionUserLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:reset-user-limit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset monthly user limit for subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::where('status', 'active')->get();
        foreach ($users as $user) {
            $subscription = SubscriptionUser::where('user_id', $user->id)->where('status', 'active')->first();
            if (!empty($subscription)) {
                $sub = Subscription::where('id', $subscription->id)->first();
                SubscriptionUser::where('status', 'active')
                    ->where('user_id', $user->id)
                    ->where('subscription_id', $sub->id)
                    ->update([
                        'user_limit' => 0
                    ]);
            }
        }
        $this->info('Resetting subscription user limits...');
        
        $this->info('User limits reset successfully.');

        return Command::SUCCESS;
    }
}
