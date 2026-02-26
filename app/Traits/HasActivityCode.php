<?php

namespace App\Traits;

use App\Models\Plan;
use App\Models\DailyReport;

trait HasActivityCode
{
    /**
     * Get the prefix map for activity types.
     */
    public static function getActivityPrefixMap()
    {
        return [
            'Call' => 'C',
            'Phone Call' => 'C',
            'Visit' => 'V',
            'Ent' => 'E',
            'Entertainment' => 'E',
            'Online Meeting' => 'OM',
            'Email' => 'EM',
            'Survey' => 'S',
            'Presentation' => 'PR',
            'Proposal' => 'PP',
            'Negotiation' => 'N',
            'Admin/Tender' => 'AT',
            'Tender' => 'AT',
            'Closing' => 'CL',
            'Other' => 'O',
        ];
    }

    /**
     * Get the reverse map for display.
     */
    public static function getActivityTypeNameMap()
    {
        return [
            'C' => 'Call',
            'V' => 'Visit',
            'E' => 'Entertainment',
            'OM' => 'Online Meeting',
            'EM' => 'Email',
            'S' => 'Survey',
            'PR' => 'Presentation',
            'PP' => 'Proposal',
            'N' => 'Negotiation',
            'AT' => 'Admin/Tender',
            'CL' => 'Closing',
            'O' => 'Other',
        ];
    }

    /**
     * Get the prefix for a given activity type.
     */
    public static function getActivityPrefix($type)
    {
        $map = self::getActivityPrefixMap();

        if (isset($map[$type])) {
            return $map[$type];
        }

        // Default logic for unknown types
        $words = explode(' ', $type);
        if (count($words) > 1) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($type, 0, 2));
    }

    /**
     * Get the activity code (e.g., C1, V2).
     * This is intended to be used as an accessor: getActivityCodeAttribute.
     */
    public function calculateActivityCode()
    {
        $prefix = self::getActivityPrefix($this->activity_type);

        // Determine which model we are dealing with to query correctly
        $modelClass = get_class($this);

        // Fetch all items of this type for this customer & product, ordered by creation (ID)
        // Note: For DailyReport, we might not have a product_id (nullable), handle it.
        $query = $modelClass::where('customer_id', $this->customer_id)
            ->where('activity_type', $this->activity_type);

        if (isset($this->product_id)) {
            $query->where('product_id', $this->product_id);
        } else {
            $query->whereNull('product_id');
        }

        $items = $query->orderBy('id', 'asc')->get();

        $counter = 1;
        $myCode = $prefix . $counter;

        foreach ($items as $index => $item) {
            if ($item->id == $this->id) {
                $myCode = $prefix . $counter;
            }

            // Status-based increment logic (specifically for Plan follow-up)
            if ($modelClass === Plan::class) {
                if ($item->status === 'reported') {
                    $counter++;
                } else if ($index === count($items) - 1) {
                    $counter++;
                }
            } else {
                // For DailyReport or others, every entry increments
                $counter++;
            }
        }

        return $myCode;
    }
}
