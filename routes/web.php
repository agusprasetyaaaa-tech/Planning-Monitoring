<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PlanningReportController; // Add this too if used later
use App\Http\Controllers\TimeSettingController; // Add this too
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Report;
use App\Models\Team;
use App\Models\TimeSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

Route::get('/', function () {
    return Inertia::render('Auth/NexusLogin', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function (Request $request) {
    $user = auth()->user();

    // For "No Planning": Count of customers assigned to this user that do NOT have a plan for current month
    // Assuming users are assigned to products/customers directly or via teams.
    // For simplicity until strict logic is defined: Count all Plans that are in 'Draft' status or similar for this user?
    // Actually, user request: "Berapa banyak yang tidak ada planning" -> How many DO NOT have planning.
    // Let's assume this means Customers (if user manages customers) or generic "Pending" count.
    // Given the context of "Planning Monitoring", let's count Plans that are "Open" or "Draft" as "Pending/No Progress Yet".
    // But user asked for "No Planning" vs "Progress Planning".

    $noPlanningCount = 0;
    $progressPlanningCount = 0;
    $warningPlanningCount = 0; // Initialize
    $completedCount = 0; // Initialize
    $onProgressCount = 0; // Initialize

    if ($user) {
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

        $cutoff = now();
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
    }

    // --- Stats for Super Admin Dashboard with Filters ---
    $totalActivitiesCount = 0;
    $activeCustomerCount = 0;
    $totalCustomerCount = 0;
    $activityDistribution = [];
    $customerHealthStats = [];
    $dailyActivityTrend = [];
    $customerActivityBreakdown = [];
    $inactiveCustomers = [];
    $activitiesTrend = [];
    $activeCustomersTrend = [];
    $totalCustomersTrend = [];

    $teams = [];
    $users = [];

    // Filters
    $startDate = request('start_date');
    $endDate = request('end_date');
    $teamId = request('team_id');
    $userId = request('user_id');

    $isPrivileged = $user->hasRole(['Super Admin', 'BOD', 'Board of Director']);
    $isManager = $user->hasRole('Manager');

    // Scoping Logic
    if (!$isPrivileged) {
        if ($isManager) {
            $mTeam = Team::where('manager_id', $user->id)->first();
            $myTeamId = $mTeam ? $mTeam->id : $user->team_id;
            $teamId = $myTeamId;

            // Validate requested userId is in team
            // Validate requested userId is in team
            if ($userId) {
                $rUser = User::find($userId);
                // Allow if user is in team OR if it is the manager himself (self-filter)
                if (!$rUser || ($rUser->team_id != $teamId && $rUser->id != $user->id)) {
                    $userId = null;
                }
            }
        } else {
            // Regular User
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

    // 1. Total Activities (PLANS)
    $activitiesQuery = Plan::query();

    if ($startDate)
        $activitiesQuery->whereDate('planning_date', '>=', $startDate);
    if ($endDate)
        $activitiesQuery->whereDate('planning_date', '<=', $endDate);

    $activitiesQuery->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
        ->where('plans.activity_type', '!=', 'ESCALATION');

    if ($userId) {
        $activitiesQuery->where('user_id', $userId);
    } elseif ($teamId) {
        $activitiesQuery->whereHas('user', function ($uq) use ($teamId, $teamManagerId) {
            $uq->where('team_id', $teamId);
            if ($teamManagerId)
                $uq->orWhere('id', $teamManagerId);
        });
    }

    $totalActivitiesCount = $activitiesQuery->count();

    // 2. Active Customers (Customers with Active Plans)
    $activeCustomersQuery = Plan::distinct('customer_id');

    if ($startDate)
        $activeCustomersQuery->whereDate('created_at', '>=', $startDate);
    if ($endDate)
        $activeCustomersQuery->whereDate('created_at', '<=', $endDate);

    if ($userId)
        $activeCustomersQuery->where('user_id', $userId)
            ->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('activity_type', '!=', 'ESCALATION');
    elseif ($teamId) {
        $activeCustomersQuery
            ->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('activity_type', '!=', 'ESCALATION')
            ->whereHas('user', function ($q) use ($teamId, $teamManagerId) {
                $q->where('team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('id', $teamManagerId);
            });
    } else {
        $activeCustomersQuery->whereNotIn('status', ['Expired', 'expired', 'Failed', 'failed'])
            ->where('activity_type', '!=', 'ESCALATION');
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

    // --- Charts & Trends ---

    // A. Activity Marketing (Count by Type - PLANS)
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

    $activityDistribution = $distQuery->select('plans.activity_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
        ->groupBy('plans.activity_type')
        ->pluck('count', 'plans.activity_type');

    // B. Daily Activity Trend (PLANS)
    $trendQuery = Plan::join('users', 'plans.user_id', '=', 'users.id')
        ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
        ->where('plans.activity_type', '!=', 'ESCALATION');

    $trendStart = $startDate ? \Carbon\Carbon::parse($startDate) : now()->subMonth()->startOfMonth();
    $trendEnd = $endDate ? \Carbon\Carbon::parse($endDate) : now()->addMonths(2)->endOfMonth();

    $trendQuery->whereDate('plans.planning_date', '>=', $trendStart)
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
        $current->addDay();
    }

    // C. Customer Health (Simplified)
    $customerHealthStats = [
        'On Track' => $activeCustomerCount,
        'Warning' => 0
    ];

    // D. Inactive Customers & Breakdown
    $timeSettings = \App\Models\TimeSetting::first();
    $planningThreshold = $timeSettings ? $timeSettings->planning_warning_threshold : 14;
    $planningTimeUnit = $timeSettings ? $timeSettings->planning_time_unit : 'Days (Production)';

    // Eager load last 5 plans to check history accurately without N+1
    // We fetch ALL status plans (removed whereNotIn constraints) to detect Rejected/Failed ones
    $inactiveCustomersQuery = \App\Models\Customer::with(['plans' => fn($q) => $q->orderBy('planning_date', 'desc')->limit(5), 'product']);

    if ($userId)
        $inactiveCustomersQuery->where('marketing_sales_id', $userId);
    elseif ($teamId) {
        $inactiveCustomersQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
            $q->where('team_id', $teamId);
            if ($teamManagerId)
                $q->orWhere('id', $teamManagerId);
        });
    }

    // Note: Inactive Customers usually shows CURRENT status, so Date Range doesn't filter the CUSTOMER list itself 
    // unless we want to see who WAS inactive then? No, standard is "Current Inactive Duration".
    // But if filtering by User/Team, we must restrict the list.

    $inactiveCustomers = $inactiveCustomersQuery->get()->map(function ($customer) use ($planningThreshold, $planningTimeUnit) {
        $plans = $customer->plans; // Collection of last 5 plans

        // 1. Find the Most Recent Past Plan (Executed or Due)
        // Plans are ordered by planning_date desc, so first one <= now is our candidate
        // If NO past plan found, it might be a new customer or all plans are future
        $lastExecutedPlan = $plans->first(function ($p) {
            return $p->planning_date && \Carbon\Carbon::parse($p->planning_date)->endOfDay()->isPast();
        });

        $hasFuturePlan = $plans->contains(function ($p) {
            return $p->planning_date && \Carbon\Carbon::parse($p->planning_date)->isFuture();
        });

        $invalidStatus = ['Rejected', 'rejected', 'Failed', 'failed'];
        $isLastPlanRejected = false;

        if ($lastExecutedPlan) {
            if (
                in_array($lastExecutedPlan->status, $invalidStatus) ||
                in_array($lastExecutedPlan->manager_status, ['rejected']) ||
                in_array($lastExecutedPlan->bod_status, ['failed'])
            ) {
                $isLastPlanRejected = true;
            }
        }

        $inactiveDuration = 0;
        $forceWarning = false;

        if ($isLastPlanRejected) {
            // RULE: If last executed plan was Rejected/Failed, FORCE WARNING.
            // Future plans do NOT redeem this until the rejected plan is fixed/revised (which should update status or replace validation).
            // Calculate inactive duration from that rejected plan's date to show how long it's been neglected.
            // Even if duration < threshold, we force is_warning = true because the attempt FAILED.
            $pDate = \Carbon\Carbon::parse($lastExecutedPlan->planning_date);
            if (str_contains($planningTimeUnit, 'Minutes')) {
                $inactiveDuration = floor($pDate->diffInMinutes(now()));
            } else {
                $inactiveDuration = floor($pDate->diffInDays(now()));
            }
            $forceWarning = true;
        } elseif ($hasFuturePlan) {
            // Clean history (Last plan OK) + Future Plan = Active (Green)
            $inactiveDuration = 0;
        } else {
            // No Future Plan + Clean Past = Normal Inactivity Calculation
            // Use lastExecutedPlan date or fallback to customer created_at
            $lastRefDate = $lastExecutedPlan ? $lastExecutedPlan->planning_date : $customer->created_at;
            $pDate = \Carbon\Carbon::parse($lastRefDate);

            if (str_contains($planningTimeUnit, 'Minutes')) {
                $inactiveDuration = floor($pDate->diffInMinutes(now()));
            } else {
                $inactiveDuration = floor($pDate->diffInDays(now()));
            }
        }

        return [
            'id' => $customer->id,
            'name' => $customer->company_name ?? 'Unknown',
            'product' => $customer->product ? $customer->product->name : null,
            'days_inactive' => (int) $inactiveDuration,
            'is_warning' => $forceWarning || ($inactiveDuration >= $planningThreshold)
        ];
    })->sortByDesc('days_inactive')->values(); // Keep as collection first for aggregation

    // C. Planning Health (Replaces Customer Health Stats)
    // Metrics: No Planning, Planning Approved, Planning Rejected, Planning Expired, Warning Planning

    // --- Define Queries ---

    // 1. Periodical Scope (Respects Date Range) - Used for Approved/Rejected
    $hpQuery = \App\Models\Plan::query();
    if ($userId) {
        $hpQuery->where('plans.user_id', $userId);
    } elseif ($teamId) {
        $hpQuery->join('users', 'plans.user_id', '=', 'users.id')
            ->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('users.id', $teamManagerId);
            })->select('plans.*');
    }
    if ($startDate)
        $hpQuery->whereDate('plans.planning_date', '>=', $startDate);
    if ($endDate)
        $hpQuery->whereDate('plans.planning_date', '<=', $endDate);

    // 2. Global Scope (Ignored Date Range) - Used for Alerts (Expired, Warning)
    $globalScopeQuery = \App\Models\Plan::query();
    if ($userId) {
        $globalScopeQuery->where('plans.user_id', $userId);
    } elseif ($teamId) {
        $globalScopeQuery->join('users', 'plans.user_id', '=', 'users.id')
            ->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('users.id', $teamManagerId);
            })->select('plans.*');
    }

    // --- Calculate Metrics ---

    // 1. No Planning (Periodical)
    $portfolioQuery = \App\Models\Customer::query();
    if ($userId) {
        $portfolioQuery->where('marketing_sales_id', $userId);
    } elseif ($teamId) {
        $portfolioQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
            $q->where('team_id', $teamId);
            if ($teamManagerId)
                $q->orWhere('id', $teamManagerId);
        });
    }
    $portfolioCount = $portfolioQuery->count();
    $noPlanningCountCalc = max(0, $portfolioCount - $activeCustomerCount);

    // 2. Planning Approved (Periodical)
    $approvedCount = (clone $hpQuery)
        ->where(function ($q) {
            $q->where('plans.status', 'Approved')
                ->orWhere('plans.manager_status', 'approved');
        })->count();

    // 3. Planning Rejected (Periodical - Enhanced Logic)
    $rejectedCount = (clone $hpQuery)
        ->where(function ($q) {
            $q->where('plans.manager_status', 'rejected')
                ->orWhere('plans.bod_status', 'failed')
                ->orWhere('plans.status', 'rejected');
        })->count();

    // 4. Planning Expired (Global Scope)
    // Expiry Settings
    $expiryValue = $timeSettings?->plan_expiry_value ?? 7;
    $expiryUnit = $timeSettings?->plan_expiry_unit ?? 'Days';
    $expiryCutoff = \App\Models\TimeSetting::testingNow();
    if (str_contains(strtolower($expiryUnit), 'hour'))
        $expiryCutoff->subHours((int) $expiryValue);
    else
        $expiryCutoff->subDays((int) $expiryValue);

    $expiredCount = (clone $globalScopeQuery)
        ->whereDoesntHave('report')
        ->where('plans.planning_date', '<', $expiryCutoff)
        ->count();

    // 5. Warning Planning (Global Scope)
    // Warning Settings
    $planningThreshold = $timeSettings ? $timeSettings->planning_warning_threshold : 14;
    $planningTimeUnit = $timeSettings ? $timeSettings->planning_time_unit : 'Days';
    $warningLimit = \App\Models\TimeSetting::testingNow();
    if (str_contains(strtolower($planningTimeUnit), 'hour'))
        $warningLimit->subHours((int) $planningThreshold);
    else
        $warningLimit->subDays((int) $planningThreshold);

    $warningHealthCount = (clone $globalScopeQuery)
        ->whereIn('plans.status', ['reported', 'approved', 'success'])
        ->where('plans.manager_status', '!=', 'rejected')
        ->where('plans.bod_status', '!=', 'failed')
        ->where('plans.updated_at', '<', $warningLimit)
        ->whereDoesntHave('report', function ($r) {
            $r->where('progress', '100%-Closing');
        })
        ->count();

    // 6. Planning Closing (Periodical)
    $closingCount = (clone $hpQuery)
        ->whereHas('report', function ($q) {
            $q->where('progress', 'like', '100%-%');
        })->count();

    $customerHealthStats = [
        'No Planning' => $noPlanningCountCalc,
        'Planning Approved' => $approvedCount,
        'Planning Rejected' => $rejectedCount,
        'Planning Rejected' => $rejectedCount,
        'Planning Expired' => $expiredCount,
        'Warning Planning' => $warningHealthCount,
        'Planning Closing' => $closingCount
    ];

    // Convert back to array for Inertia
    $inactiveCustomers = $inactiveCustomers->all();

    // --- Sparkline Trends (Filtered) ---
    // Generates last 7 days (or range) trend data for the small badges
    $trendDates = collect(range(6, 0))->map(fn($days) => now()->subDays($days)->format('Y-m-d'));

    // 1. Activities Trend
    $sQuery = Report::join('plans', 'reports.plan_id', '=', 'plans.id')
        ->join('users', 'plans.user_id', '=', 'users.id')
        ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
        ->where('plans.activity_type', '!=', 'ESCALATION');
    if ($userId)
        $sQuery->where('plans.user_id', $userId);
    elseif ($teamId) {
        $sQuery->where(function ($q) use ($teamId, $teamManagerId) {
            $q->where('users.team_id', $teamId);
            if ($teamManagerId)
                $q->orWhere('users.id', $teamManagerId);
        });
    }

    $sQuery->where('reports.created_at', '>=', now()->subDays(6)->startOfDay());

    $activitiesTrendData = $sQuery->selectRaw('DATE(reports.created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->pluck('count', 'date');
    $activitiesTrend = $trendDates->map(fn($date) => $activitiesTrendData[$date] ?? 0)->values()->toArray();

    // 2. Active Customers Trend
    $acQuery = Plan::join('users', 'plans.user_id', '=', 'users.id')
        ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
        ->where('plans.activity_type', '!=', 'ESCALATION');
    if ($userId)
        $acQuery->where('plans.user_id', $userId);
    elseif ($teamId) {
        $acQuery->where(function ($q) use ($teamId, $teamManagerId) {
            $q->where('users.team_id', $teamId);
            if ($teamManagerId)
                $q->orWhere('users.id', $teamManagerId);
        });
    }

    $acQuery->where('plans.created_at', '>=', now()->subDays(6)->startOfDay());

    $activeCustomersTrendData = $acQuery->selectRaw('DATE(plans.created_at) as date, COUNT(DISTINCT plans.customer_id) as count')
        ->groupBy('date')
        ->pluck('count', 'date');
    $activeCustomersTrend = $trendDates->map(fn($date) => $activeCustomersTrendData[$date] ?? 0)->values()->toArray();

    // 3. Total Customers Trend (Growth)
    $tcQuery = Customer::query();
    if ($userId)
        $tcQuery->where('marketing_sales_id', $userId);
    elseif ($teamId) {
        $tcQuery->whereHas('marketing', function ($q) use ($teamId, $teamManagerId) {
            $q->where('team_id', $teamId);
            if ($teamManagerId)
                $q->orWhere('id', $teamManagerId);
        });
    }

    $tcQuery->where('created_at', '>=', now()->subDays(6)->startOfDay());

    $totalCustomersTrendData = $tcQuery->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->pluck('count', 'date');
    $totalCustomersTrend = $trendDates->map(fn($date) => $totalCustomersTrendData[$date] ?? 0)->values()->toArray();

    // 5. Customer Activity Breakdown (Optimized)
    // Filter Expiry Logic based on Time Settings
    $peValue = $timeSettings->plan_expiry_value ?? 7;
    $peUnit = $timeSettings->plan_expiry_unit ?? 'Days (Production)';
    $expiredLimit = now();
    if ($peUnit === 'Hours')
        $expiredLimit->subHours($peValue);
    elseif ($peUnit === 'Minutes')
        $expiredLimit->subMinutes($peValue);
    else
        $expiredLimit->subDays($peValue);

    $breakdownQuery = Plan::join('customers', 'plans.customer_id', '=', 'customers.id')
        ->leftJoin('reports', 'plans.id', '=', 'reports.plan_id')
        ->select(
            'customers.company_name',
            \Illuminate\Support\Facades\DB::raw("CASE WHEN reports.progress LIKE '%Closing%' THEN 'Closing' ELSE plans.activity_type END as activity_group"),
            \Illuminate\Support\Facades\DB::raw('count(distinct plans.id) as count')
        )
        ->whereNotIn('plans.status', ['Expired', 'expired', 'Failed', 'failed'])
        ->where('plans.activity_type', '!=', 'ESCALATION')
        ->where(function ($q) use ($expiredLimit) {
            // Include if Handled (Reported/Success/Completed) - logic aligns with Controller 'completed' check
            $q->whereIn('plans.status', ['success', 'completed', 'reported'])
                ->orWhere('reports.progress', 'like', '100%')
                // OR Include if Active/Fresh (Created/Pending but not yet expired)
                ->orWhere(function ($sq) use ($expiredLimit) {
                $sq->where('plans.planning_date', '>=', $expiredLimit);
            });
        });

    if ($startDate)
        $breakdownQuery->whereDate('plans.planning_date', '>=', $startDate);
    if ($endDate)
        $breakdownQuery->whereDate('plans.planning_date', '<=', $endDate);

    if ($userId)
        $breakdownQuery->where('plans.user_id', $userId);
    elseif ($teamId) {
        $breakdownQuery->join('users', 'plans.user_id', '=', 'users.id')
            ->where(function ($q) use ($teamId, $teamManagerId) {
                $q->where('users.team_id', $teamId);
                if ($teamManagerId)
                    $q->orWhere('users.id', $teamManagerId);
            });
    }

    $breakdownData = $breakdownQuery->groupBy('customers.company_name', \Illuminate\Support\Facades\DB::raw("CASE WHEN reports.progress LIKE '%Closing%' THEN 'Closing' ELSE plans.activity_type END"))
        ->get();

    $customerActivityBreakdown = $breakdownData->groupBy('company_name')->map(function ($items, $name) {
        $activities = $items->pluck('count', 'activity_group')->toArray();

        // Sort: Closing should be last (appears on TOP in flex-col-reverse)
        uksort($activities, function ($a, $b) {
            if ($a === 'Closing')
                return 1;
            if ($b === 'Closing')
                return -1;
            return 0; // Keep others as is (or strcmp($a, $b) if alphanumeric sort desired)
        });

        return [
            'name' => $name,
            'activities' => $activities,
            'total' => array_sum($activities)
        ];
    })->sortByDesc('total')->take(20)->values()->all();


    return Inertia::render('NexusDashboard', [
        'totalActivitiesCount' => $totalActivitiesCount,
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

        'teams' => $teams,
        'users' => $users,
        'timeUnit' => str_contains($planningTimeUnit, 'Minutes') ? 'minutes' : 'days',
        'filters' => [
            'start_date' => request('start_date'),
            'end_date' => request('end_date'),
            'team_id' => $teamId,
            'user_id' => request('user_id') ?? $userId, // Use request value if explicit, else fallback to calculated (e.g. for Regular User)
        ],
    ]);
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- SUPER ADMIN ONLY MASTER DATA ---
    Route::group([
        'middleware' => function ($request, $next) {
            if (!$request->user() || !$request->user()->hasRole('Super Admin')) {
                abort(404);
            }
            return $next($request);
        }
    ], function () {
        Route::delete('users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
        Route::resource('users', UserController::class);

        Route::delete('roles/bulk-destroy', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::resource('roles', RoleController::class);

        Route::delete('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::resource('products', ProductController::class);

        Route::get('customers/template', [CustomerController::class, 'template'])->name('customers.template');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::delete('customers/bulk-destroy', [CustomerController::class, 'bulkDestroy'])->name('customers.bulk-destroy');
        Route::resource('customers', CustomerController::class);

        Route::delete('teams/bulk-destroy', [TeamController::class, 'bulkDestroy'])->name('teams.bulk-destroy');
        Route::resource('teams', TeamController::class);

        // Team members management
        Route::get('/teams/{team}/members', [TeamController::class, 'members'])->name('teams.members');
        Route::post('/teams/{team}/members', [TeamController::class, 'assignMember'])->name('teams.assign-member');
        Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('teams.remove-member');

        Route::get('security', [App\Http\Controllers\SecurityController::class, 'index'])->name('security.index');
        Route::patch('security', [App\Http\Controllers\SecurityController::class, 'update'])->name('security.update');
        Route::get('security/online', [App\Http\Controllers\OnlineUserController::class, 'index'])->name('security.online');
        Route::delete('security/online/clear', [App\Http\Controllers\OnlineUserController::class, 'clearHistory'])->name('security.online.clear');
    });

    // Planning
    Route::get('/planning', [App\Http\Controllers\PlanningController::class, 'index'])->name('planning.index');
    Route::get('/planning/create', [App\Http\Controllers\PlanningController::class, 'create'])->name('planning.create');
    Route::post('/planning', [App\Http\Controllers\PlanningController::class, 'store'])->name('planning.store');
    Route::patch('/planning/{plan}/control', [App\Http\Controllers\PlanningController::class, 'updateControl'])->name('planning.update-control');
    Route::patch('/planning/{plan}/monitoring', [App\Http\Controllers\PlanningController::class, 'updateMonitoring'])->name('planning.update-monitoring');
    Route::get('/planning/{plan}/status-info', [App\Http\Controllers\PlanningController::class, 'getStatusChangeInfo'])->name('planning.status-info');
    Route::get('/planning/{plan}/report/create', [App\Http\Controllers\PlanningController::class, 'createReport'])->name('planning.report.create');
    Route::post('/planning/{plan}/report', [App\Http\Controllers\PlanningController::class, 'storeReport'])->name('planning.report.store');
    Route::patch('/planning/{plan}/fail', [App\Http\Controllers\PlanningController::class, 'markAsFailed'])->name('planning.fail');
    Route::patch('/planning/{plan}/revise', [App\Http\Controllers\PlanningController::class, 'revise'])->name('planning.revise');

    // Reset status logs (Super Admin only)
    Route::delete('/planning/{plan}/reset-status-logs', [App\Http\Controllers\PlanningController::class, 'resetStatusLogs'])->name('planning.reset-status-logs');
    Route::delete('/planning/reset-all-status-logs', [App\Http\Controllers\PlanningController::class, 'resetAllStatusLogs'])->name('planning.reset-all-status-logs');

    // Planning Report
    Route::get('/planning-report', [App\Http\Controllers\PlanningReportController::class, 'index'])->name('planning-report.index');
    Route::get('/planning-report/export-excel', [App\Http\Controllers\PlanningReportController::class, 'exportExcel'])->name('planning-report.export-excel');
    Route::get('/planning-report/export-pdf', [App\Http\Controllers\PlanningReportController::class, 'exportPdf'])->name('planning-report.export-pdf');
    Route::delete('/planning-report/bulk-destroy', [App\Http\Controllers\PlanningReportController::class, 'bulkDestroy'])->name('planning-report.bulk-destroy');
    // Time Settings
    Route::get('/time-settings', [App\Http\Controllers\TimeSettingController::class, 'index'])->name('time-settings.index');
    Route::patch('/time-settings', [App\Http\Controllers\TimeSettingController::class, 'update'])->name('time-settings.update');
    // Notifications
    Route::post('/notifications/{id}/read', function ($id) {
        $notification = auth()->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return back();
    })->name('notifications.read');

    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.read-all');
});

require __DIR__ . '/auth.php';
