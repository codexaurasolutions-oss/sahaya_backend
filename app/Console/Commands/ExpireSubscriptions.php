<?php

namespace App\Console\Commands;

use App\Models\SubscriptionUser;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Mark expired subscriptions as expired';

    public function handle()
    {
        $expired = SubscriptionUser::where('status', 'active')
            ->where('end_date', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expired} subscriptions.");
        return 0;
    }
}
