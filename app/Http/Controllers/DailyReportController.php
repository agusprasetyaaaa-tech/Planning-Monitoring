<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\DailyReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class DailyReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = DailyReport::with(['user', 'customer', 'product']);

        // Role-based filtering
        if (!$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            if ($user->hasRole('Manager')) {
                $managedTeam = Team::where('manager_id', $user->id)->first();
                if ($managedTeam) {
                    $memberIds = $managedTeam->members()->pluck('id')->toArray();
                    $memberIds[] = $user->id;
                    $query->whereIn('user_id', $memberIds);
                } else {
                    $query->where('user_id', $user->id);
                }
            } else {
                $query->where('user_id', $user->id);
            }
        }

        // Search filtering
        if ($request->search) {
            $search = $request->search;
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

        // Date filtering
        if ($request->start_date) {
            $query->whereDate('report_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('report_date', '<=', $request->end_date);
        }

        // Status filtering (is_success)
        if ($request->status === 'success') {
            $query->where('is_success', true);
        } elseif ($request->status === 'failed') {
            $query->where('is_success', false);
        }

        // Customer filtering
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // User filtering
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Handle group_by
        $groupBy = $request->input('group_by', 'customer');

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

        $perPage = $request->input('perPage', 10);
        if ($perPage === 'all') {
            $allReports = $query->get();
            $reports = new \Illuminate\Pagination\LengthAwarePaginator(
                $allReports,
                $allReports->count(),
                $allReports->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $reports = $query->paginate((int) $perPage)->withQueryString();
        }

        // Get customers and users for filters
        $customersQuery = Customer::orderBy('company_name');
        if (!$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            $customersQuery->where('marketing_sales_id', $user->id);
        }

        $usersQuery = User::orderBy('name');
        if ($user->hasRole('Manager')) {
            $managedTeam = Team::where('manager_id', $user->id)->first();
            if ($managedTeam) {
                $usersQuery->whereIn('id', $managedTeam->members()->pluck('id')->push($user->id));
            }
        }

        return Inertia::render('DailyReport/Index', [
            'reports' => $reports,
            'filters' => array_merge(
                $request->only(['search', 'start_date', 'end_date', 'perPage', 'status', 'customer_id', 'user_id']),
                ['group_by' => $groupBy]
            ),
            'customers' => $customersQuery->get(),
            'products' => \App\Models\Product::all(),
            'users' => $user->hasRole(['Super Admin', 'BOD', 'Board of Director', 'Manager']) ? $usersQuery->get() : [],
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'nullable|exists:products,id',
            'report_date' => 'required|date',
            'activity_type' => 'required|string',
            'description' => 'required|string',
            'location' => 'required|string',
            'pic' => 'required|string',
            'position' => 'required|string',
            'result_description' => 'required|string',
            'progress' => 'required|string',
            'is_success' => 'required|boolean',
            'next_plan' => 'nullable|string',
        ]);

        $userId = Auth::id();
        if (Auth::user()->hasRole('Super Admin') && $request->user_id) {
            $userId = $request->user_id;
        }

        // AUTO-UPDATE CUSTOMER PRODUCT
        // If user changes product here, update the default product in Customer record
        if ($request->product_id) {
            $customer = Customer::find($request->customer_id);
            if ($customer && $customer->product_id != $request->product_id) {
                $customer->update(['product_id' => $request->product_id]);
            }
        }

        DailyReport::create(array_merge($request->all(), [
            'user_id' => $userId
        ]));

        return redirect()->route('daily-report.index')->with('success', 'Daily report submitted successfully.');
    }

    public function update(Request $request, DailyReport $dailyReport)
    {
        if (!Auth::user()->hasRole('Super Admin') && Auth::id() !== $dailyReport->user_id) {
            abort(403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'nullable|exists:products,id',
            'report_date' => 'required|date',
            'activity_type' => 'required|string',
            'description' => 'required|string',
            'location' => 'required|string',
            'pic' => 'required|string',
            'position' => 'required|string',
            'result_description' => 'required|string',
            'progress' => 'required|string',
            'is_success' => 'required|boolean',
            'next_plan' => 'nullable|string',
        ]);

        $userId = $dailyReport->user_id;
        if (Auth::user()->hasRole('Super Admin') && $request->user_id) {
            $userId = $request->user_id;
        }

        // AUTO-UPDATE CUSTOMER PRODUCT
        if ($request->product_id) {
            $customer = Customer::find($request->customer_id);
            if ($customer && $customer->product_id != $request->product_id) {
                $customer->update(['product_id' => $request->product_id]);
            }
        }

        $dailyReport->update(array_merge($request->all(), [
            'user_id' => $userId
        ]));

        return redirect()->route('daily-report.index')->with('success', 'Daily report updated successfully.');
    }

    public function destroy(DailyReport $dailyReport)
    {
        if (!Auth::user()->hasRole('Super Admin') && Auth::id() !== $dailyReport->user_id) {
            abort(403);
        }

        $dailyReport->delete();
        return back()->with('success', 'Report deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        if (!Auth::user()->hasRole('Super Admin')) {
            abort(403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:daily_reports,id'
        ]);

        DailyReport::whereIn('id', $request->ids)->delete();

        return redirect()->route('daily-report.index')->with('success', 'Selected reports deleted successfully.');
    }

    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        $filters = $request->only(['search', 'start_date', 'end_date', 'ids', 'group_by', 'customer_id', 'user_id']);
        $fileName = 'daily-report-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new DailyReportExport($filters, $user), $fileName);
    }

    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        $query = DailyReport::with(['user', 'customer', 'product']);

        // IDs filter for bulk export
        if ($request->ids) {
            $query->whereIn('id', $request->ids);
        } else {
            // Role-based filtering
            if (!$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
                if ($user->hasRole('Manager')) {
                    $managedTeam = Team::where('manager_id', $user->id)->first();
                    if ($managedTeam) {
                        $memberIds = $managedTeam->members()->pluck('id')->toArray();
                        $memberIds[] = $user->id;
                        $query->whereIn('user_id', $memberIds);
                    } else {
                        $query->where('user_id', $user->id);
                    }
                } else {
                    $query->where('user_id', $user->id);
                }
            }

            // Search filtering
            if ($request->search) {
                $search = $request->search;
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

            // Date filtering
            if ($request->start_date) {
                $query->whereDate('report_date', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $query->whereDate('report_date', '<=', $request->end_date);
            }

            // Customer filtering
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }

            // User filtering
            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Handle group_by
            $groupBy = $request->input('group_by', 'customer');

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
        }

        $reports = $query->get();

        $pdf = Pdf::loadView('exports.daily-report-pdf', compact('reports'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('daily-report-' . now()->format('Y-m-d-His') . '.pdf');
    }
}
