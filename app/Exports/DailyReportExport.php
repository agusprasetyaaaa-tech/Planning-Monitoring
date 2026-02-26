<?php

namespace App\Exports;

use App\Models\DailyReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DailyReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
        $query = DailyReport::with(['user', 'customer', 'product']);

        // Apply filters
        if (!empty($this->filters['ids'])) {
            $query->whereIn('id', $this->filters['ids']);
        } else {
            if (!empty($this->filters['search'])) {
                $search = $this->filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('activity_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('company_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if (!empty($this->filters['start_date'])) {
                $query->whereDate('report_date', '>=', $this->filters['start_date']);
            }
            if (!empty($this->filters['end_date'])) {
                $query->whereDate('report_date', '<=', $this->filters['end_date']);
            }

            // Role-based filtering
            if (!$this->user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
                if ($this->user->hasRole('Manager')) {
                    $managedTeam = \App\Models\Team::where('manager_id', $this->user->id)->first();
                    if ($managedTeam) {
                        $memberIds = $managedTeam->members()->pluck('id')->toArray();
                        $memberIds[] = $this->user->id;
                        $query->whereIn('user_id', $memberIds);
                    } else {
                        $query->where('user_id', $this->user->id);
                    }
                } else {
                    $query->where('user_id', $this->user->id);
                }
            }
        }

        // Apply sorting / grouping
        $groupBy = $this->filters['group_by'] ?? 'customer';
        if ($groupBy === 'customer') {
            $query->leftJoin('customers', 'daily_reports.customer_id', '=', 'customers.id')
                ->orderBy('customers.company_name', 'asc')
                ->orderBy('daily_reports.report_date', 'desc')
                ->orderBy('daily_reports.created_at', 'desc')
                ->select('daily_reports.*');
        } else {
            $query->orderBy('daily_reports.report_date', 'desc')
                ->orderBy('daily_reports.created_at', 'desc');
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Report Date',
            'Sales / Marketing',
            'Company',
            'Product',
            'Code',
            'Location',
            'PIC & Position',
            'Description',
            'Result',
            'Next Plan',
            'Progress',
            'Status',
        ];
    }

    public function map($report): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $report->report_date ? \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') : '-',
            $report->user->name ?? '-',
            $report->customer->company_name ?? '-',
            $report->product->name ?? '-',
            ($report->activity_code ?? '') . ' (' . ($report->activity_type ?? '-') . ')',
            $report->location ?? '-',
            ($report->pic ?? '-') . ' (' . ($report->position ?? '-') . ')',
            $report->description ?? '-',
            $report->result_description ?? '-',
            $report->next_plan ?? '-',
            $report->progress ?? '-',
            $report->is_success ? 'SUCCESS' : 'FAILED',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'], // emerald-600
                ],
            ],
        ];
    }
}
