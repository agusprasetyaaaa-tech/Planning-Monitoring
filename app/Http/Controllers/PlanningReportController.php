<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\TimeSetting;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Exports\PlanningReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanningReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Plan::with(['user', 'customer', 'product', 'report'])
            ->where('activity_type', '!=', 'ESCALATION');

        // Check user role
        $user = auth()->user();

        // Super Admin, BOD, Board of Director can see all
        if ($user && !$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            // Manager: See plans created by themselves AND their team members
            if ($user->hasRole('Manager')) {
                // Find team managed by this user
                $managedTeam = \App\Models\Team::where('manager_id', $user->id)->first();

                if ($managedTeam) {
                    // Get all team members' IDs
                    $memberIds = $managedTeam->members()->pluck('id')->toArray();
                    // Add Manager's own ID
                    $memberIds[] = $user->id;

                    $query->whereIn('user_id', $memberIds);
                } else {
                    // Fallback: If not managing any team, show only own plans
                    $query->where('user_id', $user->id);
                }
            } else {
                // Regular User: Only see their own plans
                $query->where('user_id', $user->id);
            }
        }

        // Filtering
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('activity_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('company_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('customer_id') && $request->input('customer_id') !== null && $request->input('customer_id') !== '') {
            $query->where('plans.customer_id', $request->input('customer_id'));
        }

        if ($request->has('user_id') && $request->input('user_id') !== null && $request->input('user_id') !== '') {
            $query->where('plans.user_id', $request->input('user_id'));
        }

        // Date Filtering
        if ($request->has('start_date') && $request->input('start_date')) {
            $query->whereDate('planning_date', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date') && $request->input('end_date')) {
            $query->whereDate('planning_date', '<=', $request->input('end_date'));
        }

        // Handle group_by BEFORE tab filtering to avoid ambiguous column errors
        // Default to 'customer' as requested
        $groupBy = $request->input('group_by', 'customer');

        if ($groupBy === 'customer') {
            $query->leftJoin('customers', 'plans.customer_id', '=', 'customers.id')
                ->orderBy('customers.company_name', 'asc')
                ->orderBy('plans.created_at', 'desc')
                ->select('plans.*');
        } else {
            $query->orderBy('plans.created_at', 'desc');
        }

        // Tab Filtering
        $currentTab = $request->input('tab', 'all');
        $timeSettings = TimeSetting::firstOrCreate([]);
        $expiryValue = $timeSettings->plan_expiry_value ?? 7;
        $expiryUnit = $timeSettings->plan_expiry_unit ?? 'Days (Production)';
        $warningThreshold = $timeSettings->planning_warning_threshold ?? 14;
        $planningTimeUnit = $timeSettings->planning_time_unit ?? 'Days (Production)';
        $timeOffset = $timeSettings->testing_time_offset ?? 0; // Days

        if ($currentTab !== 'all') {
            // Get all plan IDs first, then filter in PHP for complex time logic
            // This is because SQL time calculations are complex across different units
            $allPlans = (clone $query)->get();
            $filteredIds = [];

            foreach ($allPlans as $plan) {
                $status = $this->calculatePlanStatus($plan, $expiryValue, $expiryUnit, $warningThreshold, $planningTimeUnit, $timeOffset);

                if ($currentTab === 'on_track' && $status === 'on_track') {
                    $filteredIds[] = $plan->id;
                } elseif ($currentTab === 'warning' && $status === 'warning') {
                    $filteredIds[] = $plan->id;
                } elseif ($currentTab === 'failed' && $status === 'failed') {
                    $filteredIds[] = $plan->id;
                } elseif ($currentTab === 'completed' && $status === 'completed') {
                    $filteredIds[] = $plan->id;
                } elseif ($currentTab === 'history' && $plan->is_history) {
                    $filteredIds[] = $plan->id;
                }
            }

            $query->whereIn('plans.id', $filteredIds);
        }

        // Handle per page
        $perPage = $request->input('perPage', 10);
        if ($perPage === 'all') {
            $allPlans = $query->get();
            $plans = new \Illuminate\Pagination\LengthAwarePaginator(
                $allPlans,
                $allPlans->count(),
                $allPlans->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $plans = $query->paginate((int) $perPage)->onEachSide(1)->withQueryString();
        }

        $customers = \App\Models\Customer::select('id', 'company_name')->orderBy('company_name')->get();

        // Get users list for filter dropdown (based on role visibility)
        $usersQuery = \App\Models\User::select('id', 'name', 'team_id')->orderBy('name');
        if ($user && !$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            if ($user->hasRole('Manager')) {
                $managedTeam = \App\Models\Team::where('manager_id', $user->id)->first();
                if ($managedTeam) {
                    $memberIds = $managedTeam->members()->pluck('id')->toArray();
                    $memberIds[] = $user->id;
                    $usersQuery->whereIn('id', $memberIds);
                } else {
                    $usersQuery->where('id', $user->id);
                }
            } else {
                $usersQuery->where('id', $user->id);
            }
        }
        $users = $usersQuery->get();

        // MERGE group_by into filters so frontend knows the default
        $filters = array_merge(
            $request->only(['search', 'customer_id', 'user_id', 'tab', 'perPage', 'start_date', 'end_date']),
            ['group_by' => $groupBy]
        );

        return Inertia::render('PlanningReport/Index', [
            'plans' => $plans,
            'filters' => $filters,
            'timeSettings' => $timeSettings,
            'customers' => $customers,
            'users' => $users,
            'user_roles' => \Illuminate\Support\Facades\Auth::user() ? \Illuminate\Support\Facades\Auth::user()->getRoleNames() : [],
        ]);
    }

    /**
     * Calculate plan status for tab filtering
     */
    private function calculatePlanStatus($plan, $expiryValue, $expiryUnit, $warningThreshold, $planningTimeUnit, $timeOffset = 0)
    {
        // History check
        if ($plan->is_history) {
            return 'history';
        }

        // Completed: reported/success/failed status checks (already finalized)
        if (in_array($plan->status, ['reported', 'success', 'failed'])) {
            return 'completed';
        }

        // Calculate time difference
        // Use PLANNING_DATE (Activity Date) as primary reference, fallback to created_at
        $planDate = strtotime($plan->planning_date ?? $plan->created_at);

        // Calculate NOW with testing offset
        $now = time() + ($timeOffset * 86400); // Add offset days in seconds

        $diff = $now - $planDate;

        // 1. Check Failed/Expired (Based on Plan Expiry Unit)
        $diffForExpiry = $diff;
        if ($expiryUnit === 'Hours') {
            $diffForExpiry = $diff / 3600;
        } elseif ($expiryUnit === 'Minutes') {
            $diffForExpiry = $diff / 60;
        } else {
            $diffForExpiry = $diff / 86400; // Days
        }

        if ($diffForExpiry >= $expiryValue) {
            return 'failed';
        }

        // 2. Check Warning (Based on Planning Time Unit)
        $diffForWarning = $diff;
        if ($planningTimeUnit === 'Hours') {
            $diffForWarning = $diff / 3600;
        } elseif ($planningTimeUnit === 'Minutes') {
            $diffForWarning = $diff / 60;
        } else {
            $diffForWarning = $diff / 86400; // Days
        }

        if ($diffForWarning >= $warningThreshold) {
            return 'warning';
        }

        return 'on_track';
    }

    public function bulkDestroy(Request $request)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:plans,id'
        ]);

        Plan::whereIn('id', $request->ids)->delete();

        return redirect()->route('planning-report.index')->with('success', 'Selected plans have been deleted.');
    }

    /**
     * Export planning reports to Excel
     */
    public function exportExcel(Request $request)
    {
        $filters = [
            'tab' => $request->query('tab', 'all'),
            'search' => $request->query('search'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];

        $user = auth()->user();
        $fileName = 'planning-report-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(
            new PlanningReportExport($filters, $user),
            $fileName
        );
    }

    /**
     * Export planning reports to PDF
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();

        // Build query (same logic as index but without report filter)
        $query = Plan::with(['customer', 'product', 'user', 'report'])
            ->where('activity_type', '!=', 'ESCALATION');

        // Apply filters
        $tab = $request->query('tab', 'all');
        $search = $request->query('search');

        if ($search) {
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

        // Date filtering for PDF
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($startDate) {
            $query->whereDate('planning_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('planning_date', '<=', $endDate);
        }

        if ($user && !$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            if ($user->hasRole('Manager')) {
                $managedTeam = \App\Models\Team::where('manager_id', $user->id)->first();
                if ($managedTeam) {
                    $teamMembers = $managedTeam->members->pluck('id')->toArray();
                    $teamMembers[] = $user->id;
                    $query->whereIn('user_id', $teamMembers);
                } else {
                    $query->where('user_id', $user->id);
                }
            } else {
                $query->where('user_id', $user->id);
            }
        }

        // Sorting
        $groupBy = $request->query('group_by');
        if ($groupBy === 'customer') {
            $query->join('customers', 'plans.customer_id', '=', 'customers.id')
                ->orderBy('customers.company_name', 'asc')
                ->orderBy('plans.created_at', 'desc')
                ->select('plans.*');
        } else {
            $query->orderBy('plans.created_at', 'desc');
        }

        $plans = $query->get();

        $pdf = Pdf::loadView('exports.planning-report-pdf', compact('plans'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('planning-report-' . now()->format('Y-m-d-His') . '.pdf');
    }
}
