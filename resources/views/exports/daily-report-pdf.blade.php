<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Daily Report Export</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            color: #059669;
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
            background-color: #059669;
            color: white;
            padding: 8px 5px;
            font-size: 9px;
            text-align: left;
            border: 1px solid #047857;
        }

        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            font-size: 8px;
            vertical-align: top;
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
            font-weight: bold;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Daily Activity Report</h1>
        <p>Generated on: {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 8%;">Date</th>
                <th style="width: 9%;">Sales</th>
                <th style="width: 12%;">Company</th>
                <th style="width: 10%;">Code</th>
                <th style="width: 10%;">PIC</th>
                <th style="width: 12%;">Description</th>
                <th style="width: 12%;">Result</th>
                <th style="width: 12%;">Next Plan</th>
                <th style="width: 6%;">Progress</th>
                <th style="width: 6%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $index => $report)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') }}</td>
                    <td>{{ $report->user->name ?? '-' }}</td>
                    <td>
                        {{ $report->customer->company_name ?? '-' }}
                        @if($report->product)
                            <br><small style="color: #666;">({{ $report->product->name }})</small>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $report->activity_code }}</strong>
                        <br><small style="color: #666;">{{ $report->activity_type }}</small>
                        <br><small style="color: #999;">@ {{ $report->location }}</small>
                    </td>
                    <td>
                        {{ $report->pic }}
                        <br><small style="color: #999;">({{ $report->position }})</small>
                    </td>
                    <td>{{ $report->description }}</td>
                    <td>{{ $report->result_description }}</td>
                    <td>{{ $report->next_plan ?? '-' }}</td>
                    <td>{{ $report->progress }}</td>
                    <td>
                        <span class="badge badge-{{ $report->is_success ? 'success' : 'failed' }}">
                            {{ $report->is_success ? 'Success' : 'Failed' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center; color: #999;">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Total Records: {{ count($reports) }}</p>
        <p>© 2025 Planly App Created By Agus Prasetya</p>
    </div>
</body>

</html>