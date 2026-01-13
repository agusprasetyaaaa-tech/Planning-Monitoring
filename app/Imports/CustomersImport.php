<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomersImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Skip if company name is empty
        if (empty($row['company_name'])) {
            return null;
        }

        $product = Product::where('name', $row['product'])->first();
        $user = User::where('name', $row['marketing_sales'])->first();

        // Check if customer already exists to avoid duplicates (optional but good practice)
        $existingCustomer = Customer::where('company_name', $row['company_name'])
            ->where('product_id', $product?->id)
            ->first();

        if ($existingCustomer) {
            return null;
        }

        return new Customer([
            'company_name' => $row['company_name'],
            'product_id' => $product?->id,
            'marketing_sales_id' => $user?->id,
        ]);
    }
}
