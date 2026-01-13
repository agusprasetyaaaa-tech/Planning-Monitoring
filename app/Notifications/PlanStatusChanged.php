<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Plan;

class PlanStatusChanged extends Notification
{
    use Queueable;

    public $plan;
    public $status; // 'approved', 'rejected', 'escalated'
    public $actorName; // Who performed the action

    /**
     * Create a new notification instance.
     */
    public function __construct(Plan $plan, string $status, string $actorName = 'System')
    {
        $this->plan = $plan;
        $this->status = $status;
        $this->actorName = $actorName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $action = ucfirst($this->status);
        $customerName = $this->plan->customer->company_name ?? 'Unknown';

        $message = "Your Plan \"{$customerName}\" {$this->plan->activity_code} was {$this->status} by {$this->actorName}.";

        // Custom message for escalation
        if ($this->status === 'escalated') {
            $message = "Your Plan \"{$customerName}\" {$this->plan->activity_code} has been escalated to BOD.";
        }

        // Custom message for rejection
        if ($this->status === 'rejected') {
            $message = "Attention: Plan \"{$customerName}\" {$this->plan->activity_code} was REJECTED by {$this->actorName}.";
        }

        // Custom message for reported (new report submitted)
        if ($this->status === 'reported') {
            $message = "New Report: \"{$customerName}\" {$this->plan->activity_code} submitted by {$this->actorName}. Waiting for review.";
        }

        // Determine icon based on status
        $icon = 'alert';
        if ($this->status === 'approved')
            $icon = 'check';
        elseif ($this->status === 'rejected')
            $icon = 'x';
        elseif ($this->status === 'reported')
            $icon = 'document';

        // Determine color based on status
        $color = 'amber';
        if ($this->status === 'approved')
            $color = 'emerald';
        elseif ($this->status === 'rejected')
            $color = 'red';
        elseif ($this->status === 'reported')
            $color = 'blue';

        return [
            'plan_id' => $this->plan->id,
            'status' => $this->status,
            'message' => $message,
            'title' => "Plan {$action}",
            'icon' => $icon,
            'color' => $color,
            'link' => route('planning.index'), // Direct user to planning page
            'created_at' => now()->diffForHumans(),
        ];
    }
}
