<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Models\TimeSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExpirePlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and expire plans that have exceeded their time limit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting plan expiry check...');

        // 1. Get Time Settings
        $settings = TimeSetting::first();
        if (!$settings) {
            $this->error('Time settings not found.');
            return 1;
        }

        $expiryValue = $settings->plan_expiry_value ?? 7;
        $expiryUnit = $settings->plan_expiry_unit ?? 'Days';

        $this->info("Expiry threshold: {$expiryValue} {$expiryUnit}");

        // 2. Query active plans (created, not reported)
        // We can optimize query by calculating the cutoff date
        $now = Carbon::now();
        $cutoff = $now->copy();

        switch ($expiryUnit) {
            case 'Hours':
                $cutoff->subHours($expiryValue);
                break;
            case 'Minutes':
                $cutoff->subMinutes($expiryValue);
                break;
            default:
                $cutoff->subDays($expiryValue);
                break;
        }

        // Find plans created BEFORE the cutoff date matches expiry
        $expiredPlans = Plan::where('status', 'created')
            ->where('created_at', '<=', $cutoff)
            ->where('lifecycle_status', '!=', 'expired') // Optimization
            ->get();

        $count = $expiredPlans->count();
        $this->info("Found {$count} expired plans.");

        foreach ($expiredPlans as $plan) {
            // Updating the plan will trigger the Observer
            // The Observer's checkExpired() logic should confirm it returning 'expired'
            // But to be explicit and avoid loop, we can force it or just touch it.
            // Let's force update lifecycle_status to ensure consistency.

            $plan->lifecycle_status = 'expired';
            $plan->save(); // Observer saving() will also run, but we set it explicitly.

            $this->line("Plan ID {$plan->id} expired. Created: {$plan->created_at}");
        }

        $this->info('Plan expiry check completed.');
        return 0;
    }
}
