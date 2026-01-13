<?php

namespace App\Exports;

use App\Models\Plan;
use App\Models\TimeSetting;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PlanningReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;
    protected $user;

    public function __construct($filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function collection()
    {
        // Get all plans (including those without reports)
        $query = Plan::with(['customer', 'product', 'user', 'report'])
            ->where('activity_type', '!=', 'ESCALATION');

        // Apply filters (tab filter)
        if (isset($this->filters['tab']) && $this->filters['tab'] !== 'all') {
            $timeSettings = TimeSetting::first();
            $expiryValue = $timeSettings->plan_expiry_value ?? 7;
            $expiryUnit = $timeSettings->plan_expiry_unit ?? 'Days (Production)';

            $query->where(function ($q) use ($timeSettings, $expiryValue, $expiryUnit) {
                // Apply status filter based on tab
                if ($this->filters['tab'] === 'on_track') {
                    // On track logic
                    $q->where('lifecycle_status', '!=', 'failed')
                        ->where('lifecycle_status', '!=', 'expired');
                } elseif ($this->filters['tab'] === 'warning') {
                    // Warning logic
                    $q->where('lifecycle_status', 'warning');
                } elseif ($this->filters['tab'] === 'failed') {
                    $q->where('lifecycle_status', 'failed');
                } elseif ($this->filters['tab'] === 'completed') {
                    $q->whereIn('status', ['reported', 'success', 'failed']);
                }
            });
        }

        // Apply search filter
        if (isset($this->filters['search']) && $this->filters['search']) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_pic', 'like', "%{$search}%");
                })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('activity_code', 'like', "%{$search}%");
            });
        }

        // Date Filtering
        if (isset($this->filters['start_date']) && $this->filters['start_date']) {
            $query->whereDate('planning_date', '>=', $this->filters['start_date']);
        }
        if (isset($this->filters['end_date']) && $this->filters['end_date']) {
            $query->whereDate('planning_date', '<=', $this->filters['end_date']);
        }

        // Role-based filtering
        if (!$this->user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            if ($this->user->hasRole('Manager')) {
                $teamMembers = $this->user->managedTeam ?
                    $this->user->managedTeam->members->pluck('id')->toArray() : [];
                $teamMembers[] = $this->user->id;
                $query->whereIn('user_id', $teamMembers);
            } else {
                $query->where('user_id', $this->user->id);
            }
        }

        // Sorting
        if (isset($this->filters['group_by']) && $this->filters['group_by'] === 'customer') {
            $query->join('customers', 'plans.customer_id', '=', 'customers.id')
                ->orderBy('customers.company_name', 'asc')
                ->orderBy('plans.created_at', 'desc')
                ->select('plans.*');
        } else {
            $query->orderBy('plans.created_at', 'desc');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Code',
            'Input Time',
            'Lifecycle Status',
            'Sales / Marketing',
            'Company',
            'Customer PIC',
            'Position',
            'Location',
            'Product',
            'Activity',
            'Progress',
        ];
    }

    public function map($plan): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $plan->activity_code ?? '-',
            $plan->created_at ? $plan->created_at->format('d/m/Y H:i') : '-',
            ucfirst($plan->lifecycle_status ?? 'Active'),
            $plan->user->name ?? '-',
            $plan->customer->company_name ?? '-',
            $plan->report->pic ?? ($plan->customer->customer_pic ?? '-'),
            $plan->report->position ?? '-',
            $plan->report->location ?? '-',
            $plan->product->name ?? '-',
            $plan->activity_type ?? '-',
            $plan->report->progress ?? '0%',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0AA573'], // Emerald color
                ],
            ],
        ];
    }
}
