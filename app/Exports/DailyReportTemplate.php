<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyReportTemplate implements WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'report_date',
            'company_name',
            'product',
            'activity_type',
            'description',
            'location',
            'pic',
            'position',
            'result_description',
            'progress',
            'is_success',
            'next_plan',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // report_date
            'B' => 25,  // company_name
            'C' => 20,  // product
            'D' => 20,  // activity_type
            'E' => 35,  // description
            'F' => 20,  // location
            'G' => 20,  // pic
            'H' => 20,  // position
            'I' => 35,  // result_description
            'J' => 25,  // progress
            'K' => 15,  // is_success
            'L' => 30,  // next_plan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Add a second row with example/hint data
        $sheet->fromArray([
            '2026-03-09',
            'PT Example Company',
            'Product Name',
            'Visit',
            'Meeting with client to discuss project',
            'Jakarta',
            'John Doe',
            'Manager',
            'Client agreed to next steps',
            '50%-Proposal',
            'yes',
            'Follow up next week',
        ], null, 'A2');

        // Add a third row with format hints
        $sheet->fromArray([
            'YYYY-MM-DD',
            'Must match customer name in system',
            'Must match product name (optional)',
            'Call/Visit/Ent/Online Meeting/Survey/Presentation/Proposal/Negotiation/Admin-Tender/Other',
            'Activity description (required)',
            'Activity location (required)',
            'Person In Charge name (required)',
            'PIC Position (required)',
            'Result description (required)',
            '10%-Introduction / 20%-Visit / 30%-Presentation / 40%-Survey / 50%-Proposal / 75%-Confirm Budget / 90%-Tender-Nego / 100%-Closing',
            'yes or no',
            'Next plan description (optional)',
        ], null, 'A3');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ],
            2 => [
                'font' => ['italic' => false, 'color' => ['rgb' => '1F2937']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            ],
            3 => [
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '9CA3AF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
            ],
        ];
    }
}
