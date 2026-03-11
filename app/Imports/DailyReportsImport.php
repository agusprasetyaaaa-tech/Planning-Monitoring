<?php

namespace App\Imports;

use App\Models\DailyReport;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class DailyReportsImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    protected $userId;
    protected $errors = [];
    protected $rowNumber = 1;

    public function __construct($userId = null)
    {
        $this->userId = $userId ?? Auth::id();
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowNumber++;

        // Skip hint/format rows (row 3 in template starts with 'YYYY-MM-DD')
        if (isset($row['report_date']) && str_contains((string) $row['report_date'], 'YYYY')) {
            return null;
        }

        // Required field check
        if (empty($row['company_name']) || empty($row['activity_type']) || empty($row['description'])) {
            return null;
        }

        // Find customer by company_name
        $customer = Customer::where('company_name', 'like', trim($row['company_name']))->first();
        if (!$customer) {
            $this->errors[] = "Row {$this->rowNumber}: Customer '{$row['company_name']}' not found.";
            return null;
        }

        // Find product (optional)
        $product = null;
        if (!empty($row['product'])) {
            $product = Product::where('name', 'like', trim($row['product']))->first();
        }

        // Parse report_date
        $reportDate = null;
        if (!empty($row['report_date'])) {
            if (is_numeric($row['report_date'])) {
                // Handle Excel serial date
                $reportDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['report_date'])->format('Y-m-d');
            } else {
                $reportDate = date('Y-m-d', strtotime($row['report_date']));
            }
        } else {
            $reportDate = now()->format('Y-m-d');
        }

        // Normalize activity_type
        $activityType = $this->normalizeActivityType(trim($row['activity_type'] ?? ''));

        // Parse is_success
        $isSuccess = true;
        if (isset($row['is_success'])) {
            $val = strtolower(trim((string) $row['is_success']));
            $isSuccess = in_array($val, ['yes', '1', 'true', 'success', 'ya']);
        }

        // Normalize progress
        $progress = $this->normalizeProgress(trim($row['progress'] ?? ''));

        // Parse input_at (optional)
        $createdAt = null;
        if (!empty($row['input_at'])) {
            if (is_numeric($row['input_at'])) {
                $createdAt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['input_at']);
            } else {
                $createdAt = date('Y-m-d H:i:s', strtotime($row['input_at']));
            }
        }

        $reportData = [
            'user_id' => $this->userId,
            'customer_id' => $customer->id,
            'product_id' => $product?->id,
            'report_date' => $reportDate,
            'activity_type' => $activityType,
            'description' => trim($row['description'] ?? ''),
            'location' => trim($row['location'] ?? '-'),
            'pic' => trim($row['pic'] ?? '-'),
            'position' => trim($row['position'] ?? '-'),
            'result_description' => trim($row['result_description'] ?? '-'),
            'progress' => $progress,
            'is_success' => $isSuccess,
            'next_plan' => trim($row['next_plan'] ?? ''),
        ];

        if ($createdAt) {
            $reportData['created_at'] = $createdAt;
        }

        return new DailyReport($reportData);
    }

    /**
     * Normalize activity type to match system values
     */
    private function normalizeActivityType(string $type): string
    {
        $map = [
            'call' => 'Call',
            'visit' => 'Visit',
            'ent' => 'Ent',
            'online meeting' => 'Online Meeting',
            'survey' => 'Survey',
            'presentation' => 'Presentation',
            'proposal' => 'Proposal',
            'negotiation' => 'Negotiation',
            'admin/tender' => 'Admin/Tender',
            'admin-tender' => 'Admin/Tender',
            'other' => 'Other',
        ];

        return $map[strtolower($type)] ?? $type;
    }

    /**
     * Normalize progress value to match system options
     */
    private function normalizeProgress(string $progress): string
    {
        if (empty($progress))
            return '';

        // If it already contains %-format, normalize the dash to match system format
        $progress = str_replace('–', '-', $progress); // em-dash to hyphen

        $validOptions = [
            '10%-Introduction',
            '20%-Visit',
            '30%-Presentation',
            '40%-Survey',
            '50%-Proposal',
            '75%-Confirm Budget',
            '90%-Tender/Nego',
            '100%-Closing'
        ];

        // Try case-insensitive match
        foreach ($validOptions as $option) {
            if (strtolower($progress) === strtolower($option)) {
                return $option;
            }
        }

        // Try partial match (just the number)
        if (preg_match('/^(\d+)/', $progress, $matches)) {
            $num = (int) $matches[1];
            foreach ($validOptions as $option) {
                if (str_starts_with($option, $num . '%')) {
                    return $option;
                }
            }
        }

        return $progress;
    }

    /**
     * Get import errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
