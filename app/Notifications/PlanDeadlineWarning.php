<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Plan;

class PlanDeadlineWarning extends Notification
{
    use Queueable;

    public $plan;
    public $timeLeft;

    public function __construct(Plan $plan, string $timeLeft)
    {
        $this->plan = $plan;
        $this->timeLeft = $timeLeft;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'plan_id' => $this->plan->id,
            'status' => 'warning',
            'message' => "Warning: Plan {$this->plan->activity_code} is expiring in {$this->timeLeft}. Please report immediately!",
            'title' => "Deadline Warning",
            'icon' => 'clock',
            'color' => 'amber',
            'link' => route('planning.index'),
        ];
    }
}
