<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomersTemplate implements WithHeadings
{
    public function headings(): array
    {
        return [
            'Company Name',
            'Product',
            'Marketing Sales',
            'Planning Start Date',
        ];
    }
}
