<?php

namespace App\Observers;

use App\Models\Plan;
use Carbon\Carbon;
use App\Models\TimeSetting;

class PlanObserver
{
    /**
     * Handle the Plan "saving" event.
     */
    public function saving(Plan $plan)
    {
        // 1. Set Timestamps
        if ($plan->isDirty('manager_status')) {
            // Update timestamp on EVERY Manager action (not just first time)
            // This refreshes the grace period each time Manager toggles
            // Use TimeSetting::testingNow() to respect time offset during testing
            if ($plan->manager_status !== 'pending') {
                $plan->manager_reviewed_at = TimeSetting::testingNow();
            }
        }

        if ($plan->isDirty('bod_status')) {
            // Update timestamp on EVERY BOD action (not just first time)
            // This refreshes the 5-minute grace period each time BOD toggles
            // Use TimeSetting::testingNow() to respect time offset during testing
            if ($plan->bod_status !== 'pending') {
                $plan->bod_reviewed_at = TimeSetting::testingNow();
            }
        }

        // 2. Set Lifecycle Status (Sync with getLifecycleStatusLabelAttribute logic)
        $plan->lifecycle_status = $this->determineLifecycleStatus($plan);
    }

    /**
     * Determine status for DB column (lowercase snake_case)
     */
    private function determineLifecycleStatus(Plan $plan)
    {
        // 0. PRESERVE MANUAL FAIL: If already manually set to 'failed' (from markAsFailed), keep it
        // This prevents override when user clicks "Plan Failed" button
        if ($plan->isDirty('lifecycle_status') && $plan->lifecycle_status === 'failed') {
            return 'failed';
        }

        // 1. Completed (Manager Approve + BOD Success)
        if ($plan->manager_status === 'approved' && $plan->bod_status === 'success') {
            return 'completed';
        }

        // 2. REJECTED (Report submitted, but quality rejected by Manager+BOD)
        // This means user DID submit a report, but it was deemed inadequate
        if ($plan->status === 'reported' && $plan->bod_status === 'failed') {
            return 'rejected';
        }

        // 3. EXPIRED (No report submitted, time limit exceeded)
        // This means user FAILED to submit a report within time limit
        if ($plan->status === 'created' && $this->checkExpired($plan)) {
            return 'expired';
        }

        // 4. Under Review (Report submitted, pending Manager/BOD decision)
        if ($plan->status === 'reported') {
            return 'under_review';
        }

        // 5. Active (Plan created, not expired yet, no report)
        // Frontend will differentiate 'warning' state dynamically based on time threshold
        return 'active';
    }

    private function checkExpired(Plan $plan)
    {
        if ($plan->status !== 'created')
            return false;

        // Avoid DB call if possible, or cache it. 
        // For simplicity in Observer (which runs in request), DB call is acceptable.
        $settings = TimeSetting::first();
        if (!$settings)
            return false;

        $expiryValue = $settings->plan_expiry_value ?? 7;
        $expiryUnit = $settings->plan_expiry_unit ?? 'Days';

        $createdAt = $plan->created_at ?? Carbon::now(); // Handle creating event
        $now = Carbon::now();

        switch ($expiryUnit) {
            case 'Hours':
                return $now->diffInHours($createdAt) >= $expiryValue;
            case 'Minutes':
                return $now->diffInMinutes($createdAt) >= $expiryValue;
            default: // Days
                return $now->diffInDays($createdAt) >= $expiryValue;
        }
    }
}
