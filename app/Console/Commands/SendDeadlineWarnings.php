<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Notifications\PlanDeadlineWarning;
use Carbon\Carbon;
use App\Models\TimeSetting;

class SendDeadlineWarnings extends Command
{
    protected $signature = 'app:send-deadline-warnings';
    protected $description = 'Send warnings for plans approaching deadline';

    public function handle()
    {
        $now = TimeSetting::testingNow();
        $tomorrow = $now->copy()->addDay()->toDateString();
        $today = $now->toDateString();

        $this->info("Checking deadlines for: Today ($today) and Tomorrow ($tomorrow)");

        $plans = Plan::whereIn('planning_date', [$today, $tomorrow])
            ->where('status', 'created')
            ->get();

        foreach ($plans as $plan) {
            if ($plan->user) {
                // Check if already notified today (using simulated time for check)
                $alreadySent = $plan->user->notifications()
                    ->where('type', PlanDeadlineWarning::class)
                    ->where('data->plan_id', $plan->id)
                    ->whereDate('created_at', $now->toDateString())
                    ->exists();

                if (!$alreadySent) {
                    // Determine time left message
                    // Note: planning_date is Y-m-d string usually
                    $pDate = substr($plan->planning_date, 0, 10);
                    $timeLeft = ($pDate === $today) ? 'Today' : 'Tomorrow';

                    $plan->user->notify(new PlanDeadlineWarning($plan, $timeLeft));
                    $this->info("Sent warning for Plan ID {$plan->id} ({$timeLeft})");
                }
            }
        }

        $this->info('Deadline check complete.');
    }
}
