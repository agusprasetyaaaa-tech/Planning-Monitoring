<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Planning Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0AA573;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            color: #0AA573;
            font-size: 18px;
        }

        .header p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #0AA573;
            color: white;
            padding: 8px 5px;
            font-size: 9px;
            text-align: left;
            border: 1px solid #088a5e;
        }

        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            font-size: 8px;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: #999;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }

        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('logo/logo.png') }}" alt="Logo"
            style="width: 60px; height: auto; margin-bottom: 10px;">
        <h1 style="color: #666;">PLANNING REPORT</h1>
        <p>Generated on: {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 7%;">Code</th>
                <th style="width: 10%;">Input Time</th>
                <th style="width: 8%;">Lifecycle Status</th>
                <th style="width: 10%;">Sales/Marketing</th>
                <th style="width: 12%;">Company</th>
                <th style="width: 10%;">Customer PIC</th>
                <th style="width: 8%;">Position</th>
                <th style="width: 10%;">Location</th>
                <th style="width: 10%;">Product</th>
                <th style="width: 7%;">Activity</th>
                <th style="width: 5%;">Progress</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $index => $plan)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $plan->activity_code ?? '-' }}</td>
                    <td>{{ $plan->created_at ? $plan->created_at->format('d/m/Y H:i') : '-' }}</td>
                    <td>
                        <span class="badge badge-{{ strtolower($plan->lifecycle_status ?? 'active') }}">
                            {{ ucfirst($plan->lifecycle_status ?? 'Active') }}
                        </span>
                    </td>
                    <td>{{ $plan->user->name ?? '-' }}</td>
                    <td>{{ $plan->customer->company_name ?? '-' }}</td>
                    <td>{{ $plan->report->pic ?? ($plan->customer->customer_pic ?? '-') }}</td>
                    <td>{{ $plan->report->position ?? '-' }}</td>
                    <td>{{ $plan->report->location ?? '-' }}</td>
                    <td>{{ $plan->product->name ?? '-' }}</td>
                    <td>{{ $plan->activity_type ?? '-' }}</td>
                    <td>{{ $plan->report->progress ?? '0%' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align: center; color: #999;">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total Records: {{ count($plans) }}</p>
        <p>© 2025 Planly App • Created by Agus Prasetya</p>
    </div>
</body>

</html>