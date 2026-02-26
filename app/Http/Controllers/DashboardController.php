<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Plan;
use App\Models\Report;
use App\Models\Team;
use App\Models\TimeSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return \Illuminate\Support\Facades\Redirect::route('login');
        }

        // --- 1. Determine User Scope ---
        $scopeUserIds = null;
        if (!$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            if ($user->hasRole('Manager')) {
                // Manager: Self + Team
                $managedTeam = Team::where('manager_id', $user->id)->first();
                if ($managedTeam) {
                    $scopeUserIds = $managedTeam->members()->pluck('id')->toArray();
                    $scopeUserIds[] = $user->id; // Add self
                } else {
                    $scopeUserIds = [$user->id];
                }
            } else {
                // Regular User & SPV: Only see their own plans and assigned customers
                $scopeUserIds = [$user->id];
            }
        }

        // --- 2. "No Planning" Count ---
        // Customers in scope with NO plans
        $customersQuery = Customer::query();
        if ($scopeUserIds !== null) {
            $customersQuery->whereIn('marketing_sales_id', $scopeUserIds);
        }
        $noPlanningCount = (clone $customersQuery)->whereDoesntHave('plans')->count();

        // --- 3. Time Settings ---
        $timeSettings = TimeSetting::first();
        $threshold = $timeSettings ? ($timeSettings->planning_warning_threshold ?? 14) : 14;
        $unit = $timeSettings ? ($timeSettings->planning_time_unit ?? 'Days (Production)') : 'Days (Production)';

        $cutoff = Carbon::now();
        if ($unit === 'Hours') {
            $cutoff->subHours($threshold);
        } elseif ($unit === 'Minutes') {
            $cutoff->subMinutes($threshold);
        } else {
            $cutoff->subDays($threshold);
        }

        // --- 4. Warning / Late Count ---
        // Logic: Plan Valid, Unfinished, Excluding History
        $warningQuery = Plan::query()
            ->where(function ($q) {
                $q->whereDoesntHave('report')
                    ->orWhereHas('report', function ($rq) {
                        $rq->where('progress', 'not like', '100%');
                    });
            })
            ->where('planning_date', '<=', $cutoff)
            ->whereNotExists(function ($sub) {
                $sub->from('plans as p2')
                    ->whereColumn('p2.customer_id', 'plans.customer_id')
                    ->whereColumn('p2.product_id', 'plans.product_id')
                    ->whereColumn('p2.id', '>', 'plans.id');
            });

        if ($scopeUserIds !== null) {
            $warningQuery->whereIn('user_id', $scopeUserIds);
        }
        $warningPlanningCount = $warningQuery->count();

        // --- 5. Completed Count ---
        $reportsQuery = Report::where('progress', 'like', '100%');
        if ($scopeUserIds !== null) {
            $reportsQuery->whereHas('plan', function ($q) use ($scopeUserIds) {
                $q->whereIn('user_id', $scopeUserIds);
            });
        }
        $completedCount = $reportsQuery->count();

        // --- 6. On Progress Count ---
        $onProgressQuery = Plan::query()
            ->where(function ($q) {
                $q->whereDoesntHave('report')
                    ->orWhereHas('report', function ($rq) {
                        $rq->where('progress', 'not like', '100%');
                    });
            })
            ->where('planning_date', '>', $cutoff)
            ->whereNotExists(function ($sub) {
                $sub->from('plans as p2')
                    ->whereColumn('p2.customer_id', 'plans.customer_id')
                    ->whereColumn('p2.product_id', 'plans.product_id')
                    ->whereColumn('p2.id', '>', 'plans.id');
            });

        if ($scopeUserIds !== null) {
            $onProgressQuery->whereIn('user_id', $scopeUserIds);
        }
        $onProgressCount = $onProgressQuery->count();

        // --- Stats for Super Admin Dashboard with Filters ---
        $teams = [];
        $users = [];

        // Filters
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $teamId = $request->team_id;
        $userId = $request->user_id;

        $isPrivileged = $user->hasRole(['Super Admin', 'BOD', 'Board of Director']);
        $isManager = $user->hasRole('Manager');

        // Scoping Logic
        if (!$isPrivileged) {
            if ($isManager) {
                $mTeam = Team::where('manager_id', $user->id)->first();
                $myTeamId = $mTeam ? $mTeam->id : $user->team_id;
                $teamId = $myTeamId;

                if ($userId) {
                    $rUser = User::find($userId);
                    if (!$rUser || ($rUser->team_id != $teamId && $rUser->id != $user->id)) {
                        $userId = null;
                    }
                }
            } else {
                $userId = $user->id;
                $teamId = null;
            }
        }

        $teamManagerId = null;
        if ($isPrivileged || $isManager) {
            if ($isPrivileged) {
                $teams = Team::select('id', 'name')->get();
            } elseif ($isManager && $teamId) {
                $teams = Team::where('id', $teamId)->select('id', 'name')->get();
            }

            $teamManagerId = $teamId ? Team::where('id', $teamId)->value('manager_id') : null;

            $users = User::select('id', 'name', 'team_id')
                ->when($teamId, function ($q) use ($teamId, $teamManagerId) {
                    $q->where(function ($sub) use ($teamId, $teamManagerId) {
                        $sub->where('team_id', $teamId);
                        if ($teamManagerId)
                            $sub->orWhere('id', $teamManagerId);
                    });
                })
                ->get();
        }

        // 1. Total Planning (PLANS)
        $activitiesQuery = Plan::query();
        if ($startDate)
            $activitiesQuery->whereDate('planning_date', '>=', $startDate);
        if ($endDate)
            $activitiesQuery->whereDate('planning_date', '<=', $endDate);

        $activitiesQuery->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('activity_type', '!=', 'ESCALATION');

        if ($userId) {
            $activitiesQuery->where('user_id', $userId);
        } elseif ($teamId) {
            $activitiesQuery->whereHas('user', function ($uq) use ($teamId, $teamManagerId) {
                $uq->where('team_id', $teamId);
                if ($teamManagerId)
                    $uq->orWhere('id', $teamManagerId);
            });
        }
        $totalPlanningCount = $activitiesQuery->count();

        // 1b. Total Daily Activities
        $dailyActivitiesQuery = DailyReport::query();
        if ($startDate)
            $dailyActivitiesQuery->whereDate('report_date', '>=', $startDate);
        if ($endDate)
            $dailyActivitiesQuery->whereDate('report_date', '<=', $endDate);

        if ($userId) {
            $dailyActivitiesQuery->where('user_id', $userId);
        } elseif ($teamId) {
            $dailyActivitiesQuery->whereHas('user', function ($uq) use ($teamId, $teamManagerId) {
                $uq->where('team_id', $teamId);
                if ($teamManagerId)
                    $uq->orWhere('id', $teamManagerId);
            });
        }
        $totalDailyActivitiesCount = $dailyActivitiesQuery->count();

        // 2. Active Customers
        $activeCustomersQuery = Plan::distinct('customer_id');
        if ($startDate)
            $activeCustomersQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate)
            $activeCustomersQuery->whereDate('created_at', '<=', $endDate);

        $activeCustomersQuery->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('activity_type', '!=', 'ESCALATION');

        if ($userId)
            $activeCustomersQuery->where('user_id', $userId);
        elseif ($teamId) {
            $activeCustomersQuery->whereHas('user', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('id', $teamManagerId);
            });
        }
        $activeCustomerCount = $activeCustomersQuery->count('customer_id');

        // 3. Total Customers
        $totalCustomersQuery = Customer::query();
        if ($startDate)
            $totalCustomersQuery->whereDate('created_at', '>=', $startDate);
        if ($endDate)
            $totalCustomersQuery->whereDate('created_at', '<=', $endDate);
        if ($userId)
            $totalCustomersQuery->where('marketing_sales_id', $userId);
        elseif ($teamId) {
            $totalCustomersQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('id', $teamManagerId);
            });
        }
        $totalCustomerCount = $totalCustomersQuery->count();

        // Activity Distribution
        $distQuery = Plan::join('users', 'plans.user_id', '=', 'users.id')
            ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('plans.activity_type', '!=', 'ESCALATION');

        if ($startDate)
            $distQuery->whereDate('plans.planning_date', '>=', $startDate);
        if ($endDate)
            $distQuery->whereDate('plans.planning_date', '<=', $endDate);
        if ($userId)
            $distQuery->where('plans.user_id', $userId);
        elseif ($teamId) {
            $distQuery->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('users.id', $teamManagerId);
            });
        }
        $planDist = $distQuery->select('plans.activity_type', DB::raw('count(*) as count'))
            ->groupBy('plans.activity_type')
            ->pluck('count', 'plans.activity_type');

        $drQuery = DailyReport::join('users', 'daily_reports.user_id', '=', 'users.id');
        if ($startDate)
            $drQuery->whereDate('daily_reports.report_date', '>=', $startDate);
        if ($endDate)
            $drQuery->whereDate('daily_reports.report_date', '<=', $endDate);
        if ($userId)
            $drQuery->where('daily_reports.user_id', $userId);
        elseif ($teamId) {
            $drQuery->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId)
                    ->orWhere('users.id', $teamManagerId);
            });
        }
        $drDist = $drQuery->select('activity_type', DB::raw('count(*) as count'))
            ->groupBy('activity_type')
            ->pluck('count', 'activity_type');

        $combinedDist = [];
        foreach ($planDist as $type => $count) {
            $prefix = Plan::getActivityPrefix($type);
            $combinedDist[$prefix] = ($combinedDist[$prefix] ?? 0) + $count;
        }
        foreach ($drDist as $type => $count) {
            $prefix = DailyReport::getActivityPrefix($type);
            $combinedDist[$prefix] = ($combinedDist[$prefix] ?? 0) + $count;
        }
        $activityDistribution = $combinedDist;

        // Daily Activity Trend
        $trendStart = $startDate ? Carbon::parse($startDate) : Carbon::now()->subMonth()->startOfMonth();
        $trendEnd = $endDate ? Carbon::parse($endDate) : Carbon::now()->addMonths(2)->endOfMonth();

        $trendQuery = Plan::join('users', 'plans.user_id', '=', 'users.id')
            ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('plans.activity_type', '!=', 'ESCALATION')
            ->whereDate('plans.planning_date', '>=', $trendStart)
            ->whereDate('plans.planning_date', '<=', $trendEnd);

        if ($userId)
            $trendQuery->where('plans.user_id', $userId);
        elseif ($teamId) {
            $trendQuery->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('users.id', $teamManagerId);
            });
        }
        $dailyActivityTrendData = $trendQuery->selectRaw('DATE(plans.planning_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $dailyActivityTrend = collect();
        $current = $trendStart->copy();
        while ($current <= $trendEnd) {
            $dateStr = $current->format('Y-m-d');
            $dailyActivityTrend->push([
                'date' => $dateStr,
                'count' => isset($dailyActivityTrendData[$dateStr]) ? (int) $dailyActivityTrendData[$dateStr]->count : 0
            ]);
            $current = $current->addDay();
        }

        // Inactive Customers
        $inactiveCustomersQuery = Customer::with(['plans' => fn($q) => $q->orderBy('planning_date', 'desc')->limit(5), 'product']);
        if ($userId)
            $inactiveCustomersQuery->where('marketing_sales_id', $userId);
        elseif ($teamId) {
            $inactiveCustomersQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('id', $teamManagerId);
            });
        }

        $planningThreshold = $timeSettings ? $timeSettings->planning_warning_threshold : 14;
        $planningTimeUnit = $timeSettings ? $timeSettings->planning_time_unit : 'Days';

        $inactiveCustomers = $inactiveCustomersQuery->get()->map(function ($customer) use ($planningThreshold, $planningTimeUnit) {
            $plans = $customer->plans;
            $lastExecutedPlan = $plans->first(fn($p) => $p->planning_date && Carbon::parse($p->planning_date)->endOfDay()->isPast());
            $hasFuturePlan = $plans->contains(fn($p) => $p->planning_date && Carbon::parse($p->planning_date)->isFuture());

            $invalidStatus = ['Rejected', 'rejected', 'Failed', 'failed'];
            $isLastPlanRejected = $lastExecutedPlan && (
                in_array($lastExecutedPlan->status, $invalidStatus) ||
                in_array($lastExecutedPlan->manager_status, ['rejected']) ||
                in_array($lastExecutedPlan->bod_status, ['failed'])
            );

            $inactiveDuration = 0;
            $forceWarning = false;

            if ($isLastPlanRejected) {
                $pDate = Carbon::parse($lastExecutedPlan->planning_date);
                $inactiveDuration = str_contains($planningTimeUnit, 'Minutes') ? (int) floor($pDate->diffInMinutes(now())) : (int) floor($pDate->diffInDays(now()));
                $forceWarning = true;
            } elseif ($hasFuturePlan) {
                $inactiveDuration = 0;
            } else {
                $lastRefDate = $lastExecutedPlan ? $lastExecutedPlan->planning_date : ($customer->planning_start_date ?? $customer->created_at);
                $pDate = Carbon::parse($lastRefDate);
                $inactiveDuration = str_contains($planningTimeUnit, 'Minutes') ? (int) floor($pDate->diffInMinutes(now())) : (int) floor($pDate->diffInDays(now()));
            }

            return [
                'id' => $customer->id,
                'name' => $customer->company_name ?? 'Unknown',
                'product' => $customer->product ? $customer->product->name : null,
                'days_inactive' => (int) $inactiveDuration,
                'is_warning' => $forceWarning || ($inactiveDuration >= $planningThreshold)
            ];
        })->sortByDesc('days_inactive')->values()->all();

        // Planning Health Metrics
        $hpQuery = Plan::query();
        if ($userId)
            $hpQuery->where('user_id', $userId);
        elseif ($teamId) {
            $hpQuery->whereHas('user', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId)->orWhere('id', $teamManagerId);
            });
        }
        if ($startDate)
            $hpQuery->whereDate('planning_date', '>=', $startDate);
        if ($endDate)
            $hpQuery->whereDate('planning_date', '<=', $endDate);

        $portfolioQuery = Customer::query();
        if ($userId)
            $portfolioQuery->where('marketing_sales_id', $userId);
        elseif ($teamId) {
            $portfolioQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId)->orWhere('id', $teamManagerId);
            });
        }
        $portfolioCount = $portfolioQuery->count();
        $noPlanningCountCalc = max(0, $portfolioCount - $activeCustomerCount);

        $approvedCount = (clone $hpQuery)->where(fn($q) => $q->where('status', 'Approved')->orWhere('manager_status', 'approved'))->count();
        $rejectedCount = (clone $hpQuery)->where(fn($q) => $q->where('manager_status', 'rejected')->orWhere('bod_status', 'failed')->orWhere('status', 'rejected'))->count();

        $expiryValue = $timeSettings?->plan_expiry_value ?? 7;
        $expiryUnit = $timeSettings?->plan_expiry_unit ?? 'Days';
        $expiryCutoff = TimeSetting::testingNow();
        if (str_contains(strtolower($expiryUnit), 'hour'))
            $expiryCutoff->subHours((int) $expiryValue);
        else
            $expiryCutoff->subDays((int) $expiryValue);

        $expiredCount = Plan::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($teamId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
            ->whereDoesntHave('report')
            ->where('planning_date', '<', $expiryCutoff)
            ->count();

        $warningHealthCount = Plan::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($teamId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
            ->whereIn('status', ['reported', 'approved', 'success'])
            ->where('manager_status', '!=', 'rejected')
            ->where('bod_status', '!=', 'failed')
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('report', fn($r) => $r->where('progress', '100%-Closing'))
            ->count();

        $closingCount = (clone $hpQuery)->whereHas('report', fn($q) => $q->where('progress', 'like', '100%-%'))->count();

        $customerHealthStats = [
            'No Planning' => $noPlanningCountCalc,
            'Planning Approved' => $approvedCount,
            'Planning Rejected' => $rejectedCount,
            'Planning Expired' => $expiredCount,
            'Warning Planning' => $warningHealthCount,
            'Planning Closing' => $closingCount
        ];

        // Sparklines
        $trendDates7 = collect(range(6, 0))->map(fn($days) => Carbon::now()->subDays($days)->format('Y-m-d'));
        $activitiesTrend = $trendDates7->map(function ($date) use ($userId, $teamId, $teamManagerId) {
            return Report::whereDate('created_at', $date)
                ->whereHas('plan', function ($q) use ($userId, $teamId, $teamManagerId) {
                    $q->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
                        ->where('activity_type', '!=', 'ESCALATION')
                        ->when($userId, fn($sq) => $sq->where('user_id', $userId))
                        ->when($teamId, fn($sq) => $sq->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)));
                })->count();
        })->toArray();

        $activeCustomersTrend = $trendDates7->map(function ($date) use ($userId, $teamId, $teamManagerId) {
            return Plan::whereDate('created_at', $date)
                ->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
                ->where('activity_type', '!=', 'ESCALATION')
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->when($teamId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
                ->distinct('customer_id')->count('customer_id');
        })->toArray();

        $totalCustomersTrend = $trendDates7->map(function ($date) use ($userId, $teamId, $teamManagerId) {
            return Customer::whereDate('created_at', $date)
                ->when($userId, fn($q) => $q->where('marketing_sales_id', $userId))
                ->when($teamId, fn($q) => $q->whereHas('marketing', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
                ->count();
        })->toArray();

        // Customer Activity Breakdown
        $planBreakdownData = Plan::join('customers', 'plans.customer_id', '=', 'customers.id')
            ->leftJoin('reports', 'plans.id', '=', 'reports.plan_id')
            ->select('customers.company_name', 'plans.activity_type', 'reports.progress', DB::raw('count(distinct plans.id) as count'))
            ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('plans.activity_type', '!=', 'ESCALATION')
            ->where(fn($q) => $q->whereIn('plans.status', ['success', 'completed', 'reported'])->orWhere('reports.progress', 'like', '100%')->orWhere('plans.planning_date', '>=', $expiryCutoff))
            ->when($startDate, fn($q) => $q->whereDate('plans.planning_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('plans.planning_date', '<=', $endDate))
            ->when($userId, fn($q) => $q->where('plans.user_id', $userId))
            ->when($teamId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
            ->groupBy('customers.company_name', 'plans.activity_type', 'reports.progress')->get();

        $drBreakdownData = DailyReport::join('customers', 'daily_reports.customer_id', '=', 'customers.id')
            ->select('customers.company_name', 'daily_reports.activity_type', 'daily_reports.progress', DB::raw('count(*) as count'))
            ->when($startDate, fn($q) => $q->whereDate('daily_reports.report_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('daily_reports.report_date', '<=', $endDate))
            ->when($userId, fn($q) => $q->where('daily_reports.user_id', $userId))
            ->when($teamId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('team_id', $teamId)->orWhere('id', $teamManagerId)))
            ->groupBy('customers.company_name', 'daily_reports.activity_type', 'daily_reports.progress')->get();

        $customerActivityBreakdown = [];
        $parseProgress = fn($p) => preg_match('/(\d+)/', $p ?? '', $m) ? (int) $m[1] : 0;

        foreach ($planBreakdownData as $row) {
            $name = $row->company_name;
            $isClosing = str_contains($row->progress ?? '', '100%') || str_contains($row->progress ?? '', 'Closing');
            $code = $isClosing ? 'CL' : Plan::getActivityPrefix($row->activity_type);
            if (!isset($customerActivityBreakdown[$name]))
                $customerActivityBreakdown[$name] = ['name' => $name, 'activities' => [], 'total' => 0, 'max_progress' => 0];
            $customerActivityBreakdown[$name]['activities'][$code] = ($customerActivityBreakdown[$name]['activities'][$code] ?? 0) + $row->count;
            $customerActivityBreakdown[$name]['total'] += $row->count;
            $customerActivityBreakdown[$name]['max_progress'] = max($customerActivityBreakdown[$name]['max_progress'], $parseProgress($row->progress));
        }

        foreach ($drBreakdownData as $row) {
            $name = $row->company_name;
            $isClosing = str_contains($row->progress ?? '', '100%') || str_contains($row->progress ?? '', 'Closing');
            $code = $isClosing ? 'CL' : DailyReport::getActivityPrefix($row->activity_type);
            if (!isset($customerActivityBreakdown[$name]))
                $customerActivityBreakdown[$name] = ['name' => $name, 'activities' => [], 'total' => 0, 'max_progress' => 0];
            $customerActivityBreakdown[$name]['activities'][$code] = ($customerActivityBreakdown[$name]['activities'][$code] ?? 0) + $row->count;
            $customerActivityBreakdown[$name]['total'] += $row->count;
            $customerActivityBreakdown[$name]['max_progress'] = max($customerActivityBreakdown[$name]['max_progress'], $parseProgress($row->progress));
        }

        $customerActivityBreakdown = collect($customerActivityBreakdown)->map(function ($data) {
            uksort($data['activities'], fn($a, $b) => ($a === 'CL') ? 1 : (($b === 'CL') ? -1 : strcmp($a, $b)));
            return $data;
        })->sortByDesc('total')->take(20)->values()->all();

        return Inertia::render('NexusDashboard', [
            'totalPlanningCount' => $totalPlanningCount,
            'totalDailyActivitiesCount' => $totalDailyActivitiesCount,
            'activeCustomerCount' => $activeCustomerCount,
            'totalCustomerCount' => $totalCustomerCount,
            'activityDistribution' => $activityDistribution,
            'customerHealthStats' => $customerHealthStats,
            'dailyActivityTrend' => $dailyActivityTrend,
            'inactiveCustomers' => $inactiveCustomers,
            'customerActivityBreakdown' => $customerActivityBreakdown,
            'activitiesTrend' => $activitiesTrend,
            'activeCustomersTrend' => $activeCustomersTrend,
            'totalCustomersTrend' => $totalCustomersTrend,
            'noPlanningCount' => $noPlanningCount,
            'warningPlanningCount' => $warningPlanningCount,
            'lateRejectedCount' => $warningHealthCount + $rejectedCount + $expiredCount,
            'completedCount' => $completedCount ?? 0,
            'closingCount' => $closingCount ?? 0,
            'onProgressCount' => $onProgressCount ?? 0,
            'activity_type_map' => Plan::getActivityTypeNameMap(),
            'teams' => $teams,
            'users' => $users,
            'timeUnit' => str_contains($planningTimeUnit, 'Minutes') ? 'minutes' : 'days',
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'team_id' => $teamId,
                'user_id' => $request->user_id ?? $userId
            ]
        ]);
    }
}
