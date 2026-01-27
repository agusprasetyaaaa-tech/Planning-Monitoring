<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Report;
use App\Models\Team;
use App\Models\TimeSetting;
use App\Models\User;
use App\Models\PlanStatusLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


use App\Notifications\PlanStatusChanged;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        // Base Query - Load common relations
        $query = Customer::with([
            'product',
            'marketing',
        ]);

        // Get authenticated user for filtering
        $user = Auth::user();

        // Search filter
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('company_name', 'like', '%' . $request->search . '%')
                    ->orWhereHas('product', function ($q2) use ($request) {
                        $q2->where('name', 'like', '%' . $request->search . '%');
                    })
                    ->orWhereHas('marketing', function ($q3) use ($request) {
                        $q3->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Filter by team
        if ($request->team) {
            // Privileged users (Super Admin / BOD) can filter by any team
            if ($user->hasRole(['Super Admin', 'Board of Director', 'BOD'])) {
                $query->whereHas('marketing', function ($q) use ($request) {
                    $q->where('team_id', $request->team);
                });
            } else {
                // Restricted users can only filter within their allowed scope
                $query->whereHas('marketing', function ($q) use ($request) {
                    $q->where('team_id', $request->team)
                        ->orWhereHas('managedTeams', function ($mq) use ($request) {
                            $mq->where('id', $request->team);
                        });
                });
            }
        }

        // Filter by user (marketing_sales_id)
        if ($request->user) {
            $query->where('marketing_sales_id', $request->user);
        }



        // Role-based filtering constraints
        if (!$user->hasRole(['Super Admin', 'BOD', 'Board of Director', 'Manager'])) {
            $query->where('marketing_sales_id', $user->id);
        }
        if ($user->hasRole('Manager') && !$user->hasRole(['Super Admin', 'BOD', 'Board of Director'])) {
            $managedTeam = Team::where('manager_id', $user->id)->first();
            $pTeamId = $managedTeam ? $managedTeam->id : $user->team_id;

            if ($pTeamId) {
                $query->whereHas('marketing', function ($q) use ($pTeamId, $user) {
                    $q->where('team_id', $pTeamId)->orWhere('id', $user->id);
                });
                if (!$request->team) {
                    $request->merge(['team' => $pTeamId]);
                }
            }
        }

        // --- TAB LOGIC: WEEKS (CALENDAR BASED) ---
        $month = $request->input('month', TimeSetting::testingNow()->month);
        $year = $request->input('year', TimeSetting::testingNow()->year);
        $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // Generate Calendar Weeks
        $ranges = [];
        $currentDate = $startOfMonth->copy();
        $weekCount = 1;

        // Loop through weeks until end of month
        while ($currentDate->lte($endOfMonth)) {
            $weekStart = $currentDate->copy();

            // Week ends on the next Saturday, or end of month if sooner
            // If week starts on Sunday, next Saturday is 6 days away.
            // If week starts on Thursday (like Jan 1 2026), next Saturday is 2 days away.
            $weekEnd = $currentDate->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

            if ($weekEnd->gt($endOfMonth)) {
                $weekEnd = $endOfMonth->copy();
            }

            // Key for the ranges array
            $key = 'week' . $weekCount;

            // If we are beyond week 5, just merge into week 5 (or handle as week 6 if UI supports it, currently merging to 5 for safety)
            if ($weekCount > 5) {
                // Extend week 5 end date to cover the rest
                $ranges['week5'][1] = $endOfMonth->toDateString();
            } else {
                $ranges[$key] = [$weekStart->toDateString(), $weekEnd->toDateString()];
            }

            // Move to next Sunday
            $currentDate = $weekEnd->copy()->addDay()->startOfDay();
            $weekCount++;
        }

        // Fill missing weeks up to 5 if month is short (just for safety to avoid undefined index)
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($ranges['week' . $i])) {
                // Fallback: copy previous week's end or just use end of month
                $ranges['week' . $i] = [$endOfMonth->toDateString(), $endOfMonth->toDateString()];
            }
        }

        // Determine Current Tab
        if ($request->has('tab')) {
            $currentTab = $request->input('tab');
        } else {
            $today = TimeSetting::testingNow()->toDateString();
            $currentTab = 'week1'; // Default
            foreach ($ranges as $key => $range) {
                if ($today >= $range[0] && $today <= $range[1]) {
                    $currentTab = $key;
                    break;
                }
            }
        }

        // Apply Tab-Specific Logic
        if ($currentTab !== 'all' && isset($ranges[$currentTab])) {
            $range = $ranges[$currentTab];
            $filterEndDate = $range[1];

            // 1. Filter Customers who have specific plans in this range
            // Include all plans (including ESCALATION) to ensure visibility in weekly tabs
            $query->whereHas('plans', function ($q) use ($range) {
                $q->whereBetween('planning_date', $range);
            });

            // 2. Load 'plans' (History) filtered by End Date of the Week
            // Show all past plans up to this week, but hide future plans relative to this week
            $query->with([
                'plans' => function ($q) use ($filterEndDate) {
                    $q->where('planning_date', '<=', $filterEndDate)
                        ->with(['user', 'report', 'product', 'statusLogs.user'])
                        ->orderBy('planning_date', 'desc')
                        ->orderBy('id', 'desc');
                }
            ]);
        } else {
            // Default "All": Load standard latestPlan
            $query->with([
                'latestPlan.user',
                'latestPlan.product',
                'latestPlan.report',
                'plans' => function ($q) use ($month, $year) {
                    $q->whereYear('planning_date', $year)
                        ->whereMonth('planning_date', $month)
                        ->with(['user', 'report', 'product', 'statusLogs.user'])
                        ->orderBy('planning_date', 'desc')
                        ->orderBy('id', 'desc');
                }
            ]);
        }

        // Calculate Counts (Skipped for performance)

        // --- SORTING LOGIC ---
        if ($request->filled('sort') && $request->filled('direction')) {
            $query->orderBy($request->sort, $request->direction);
        } else {
            // Default Sorting:
            // 1. Customers who have plans IN THIS CURRENT PERIOD (Month) first
            // 2. Then sort by latest planning date within period

            $SortStartDate = $startOfMonth->toDateString();
            $SortEndDate = $endOfMonth->toDateString();

            // Re-use logic for sorting by relevance in current month
            $query->orderByRaw("
                 CASE WHEN (
                     SELECT COUNT(*) FROM plans 
                     WHERE plans.customer_id = customers.id 
                     AND plans.planning_date BETWEEN '$SortStartDate' AND '$SortEndDate'
                     AND plans.activity_type != 'ESCALATION'
                 ) > 0 THEN 0 ELSE 1 END
             ")
                ->orderByRaw("
                 (SELECT MAX(planning_date) FROM plans 
                 WHERE plans.customer_id = customers.id
                 AND plans.planning_date BETWEEN '$SortStartDate' AND '$SortEndDate'
                 AND plans.activity_type != 'ESCALATION'
                 ) DESC
             ")
                ->orderBy('company_name', 'asc');
        }

        // Pagination - Default to 'all' for All Planning tab
        $defaultPerPage = ($currentTab === 'all') ? 'all' : 10;
        $perPage = $request->input('perPage', $defaultPerPage);
        if ($perPage === 'all') {
            $allCustomers = $query->get();
            $paginatedCustomers = new \Illuminate\Pagination\LengthAwarePaginator(
                $allCustomers,
                $allCustomers->count(),
                $allCustomers->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $paginatedCustomers = $query->paginate((int) $perPage)->onEachSide(1)->withQueryString();
        }

        // POST-PROCESSING: OVERRIDE latestPlan FOR WEELY TABS
        if ($currentTab !== 'all' && isset($ranges[$currentTab])) {
            $range = $ranges[$currentTab];
            $paginatedCustomers->getCollection()->transform(function ($customer) use ($range) {
                // Find the latest plan specifically for this week range from the full list
                $relevantPlan = $customer->plans->first(function ($plan) use ($range) {
                    $elmDate = substr((string) $plan->planning_date, 0, 10); // Ensure YYYY-MM-DD string
                    return $elmDate >= $range[0] && $elmDate <= $range[1];
                });

                // Set this filtered plan as the 'latestPlan' for frontend compatibility
                $customer->setRelation('latestPlan', $relevantPlan);

                return $customer;
            });

            // FILTER OUT customers without valid plans in this week
            // Only show customers with plans that have dates (latestPlan not null)
            // AND exclude ESCALATION plans that are not actively escalated
            $filteredCollection = $paginatedCustomers->getCollection()->filter(function ($customer) {
                // No plan at all - don't show in week tab
                if ($customer->latestPlan === null) {
                    return false;
                }

                // If it's an ESCALATION plan but not in 'escalated' status (e.g., cancelled/pending),
                // don't show in week tab - these should only appear in All Planning
                if (
                    $customer->latestPlan->activity_type === 'ESCALATION'
                    && $customer->latestPlan->manager_status !== 'escalated'
                ) {
                    return false;
                }

                // Valid plan with date - show in week tab
                return true;
            })->values(); // Re-index array

            // Update the paginator with filtered collection
            $paginatedCustomers->setCollection($filteredCollection);


        } else {
            // Logic for 'All' tab: Prioritize plans with ACTUAL PROGRESS over future plans
            // Show plans that have reports (current work) instead of just showing newest plan by date
            $today = TimeSetting::testingNow()->format('Y-m-d');
            $paginatedCustomers->getCollection()->transform(function ($customer) use ($today) {
                if ($customer->plans && $customer->plans->isNotEmpty()) {
                    // PRIORITY 1: Find plan with report (actual progress) - this is current active work
                    $planWithReport = $customer->plans->first(function ($plan) {
                        return $plan->status === 'reported'; // Has actual report/activity
                    });

                    if ($planWithReport) {
                        $customer->setRelation('latestPlan', $planWithReport);
                        return $customer;
                    }

                    // PRIORITY 2: Find plan that is active today or in the past (not future)
                    $activePlan = $customer->plans->first(function ($plan) use ($today) {
                        $planDate = substr((string) $plan->planning_date, 0, 10);
                        return $planDate <= $today && $plan->status === 'created';
                    });

                    if ($activePlan) {
                        $customer->setRelation('latestPlan', $activePlan);
                        return $customer;
                    }

                    // PRIORITY 3: Fallback to absolute latest plan (future plan)
                    $absoluteLatest = $customer->plans->first();
                    $customer->setRelation('latestPlan', $absoluteLatest);
                }

                return $customer;
            });
        }

        // REMINDER DATA LOGIC
        // Get ALL customers for reminder (without pagination)
        $allCustomersQuery = Customer::with(['latestPlan.report', 'product']);

        if (!$user->hasRole('Super Admin')) {
            // For ALL roles (Manager, Supervisor, User), if not Super Admin, 
            // only show Personal Data for "No Planning", "Rejected", "Expired".
            // Manager's Team Approvals are handled separately.
            $allCustomersQuery->where('marketing_sales_id', $user->id);
        }

        // Count customers without planning
        $customersWithoutPlanning = (clone $allCustomersQuery)->whereDoesntHave('plans')->count();

        // Get plans needing report (created but no report, past date)
        $plansNeedingReport = (clone $allCustomersQuery)
            ->whereHas('latestPlan', function ($q) {
                $q->whereDoesntHave('report')
                    ->where('planning_date', '<', TimeSetting::testingNow()->toDateString());
            })->count();

        // Count BOD Review (Pending BOD OR NULL bod_status where Manager Approved)
        $bodReviewCount = Plan::where(function ($q) {
            $q->where('bod_status', 'pending')
                ->orWhereNull('bod_status');
        })
            ->where('manager_status', 'approved')
            ->whereHas('report') // Must have a report to review
            ->count();

        // Count Manager Approval (Pending Manager, Reported, AND has actual report)
        // Count Manager Approval (Pending Manager, Reported, AND has actual report)
        $managerApprovalCount = 0;
        if ($user->hasRole('Manager')) {
            $managedTeamIds = Team::where('manager_id', $user->id)->pluck('id');

            if ($managedTeamIds->isNotEmpty()) {
                $managerApprovalCount = Plan::where('manager_status', 'pending')
                    ->where('status', 'reported')
                    ->whereHas('report')
                    // Ensure plan belongs to a user in the Manager's Team
                    ->whereHas('customer.marketing', function ($q) use ($managedTeamIds) {
                        $q->whereIn('team_id', $managedTeamIds);
                    })
                    ->count();
            }
        }

        // Logic for Rejected and Late Follow-ups for Reminder
        $timeSettings = TimeSetting::first();

        // Count Rejected/Failed Plans (Revisi Needed)
        // Count Rejected/Failed Plans (Revisi Needed)
        // Count actual PLANS, not Customers, to ensure all rejected items are captured
        $plansRejected = Plan::query()
            ->whereMonth('planning_date', $month)
            ->whereYear('planning_date', $year)
            ->where(function ($q) {
                $q->where('manager_status', 'rejected')
                    ->orWhere('bod_status', 'failed')
                    ->orWhere('status', 'rejected');
            })
            ->whereHas('customer', function ($q) use ($user) {
                if (!$user->hasRole('Super Admin')) {
                    $q->where('marketing_sales_id', $user->id);
                }
            })
            ->count();

        // Count Late Follow-up (Yellow Warning - Inactivity)
        $warningThreshold = $timeSettings?->planning_warning_threshold ?? 14;
        $warningUnit = $timeSettings?->planning_time_unit ?? 'Days';

        $limitDate = TimeSetting::testingNow();

        // Simple unit handling
        if (str_contains(strtolower($warningUnit), 'minute')) {
            $limitDate->subMinutes((int) $warningThreshold);
        } elseif (str_contains(strtolower($warningUnit), 'hour')) {
            $limitDate->subHours((int) $warningThreshold);
        } else {
            $limitDate->subDays((int) $warningThreshold);
        }
        $limitDateStr = $limitDate->toDateTimeString();

        $plansLateFollowUp = (clone $allCustomersQuery)
            ->whereHas('latestPlan', function ($q) use ($limitDateStr) {
                $q->whereIn('status', ['reported', 'approved', 'success'])
                    ->where('manager_status', '!=', 'rejected')
                    ->where('bod_status', '!=', 'failed')
                    ->where('updated_at', '<', $limitDateStr)
                    ->where('activity_type', '!=', 'ESCALATION')
                    // Exclude closed plans (100%-Closing)
                    ->whereDoesntHave('report', function ($r) {
                        $r->where('progress', '100%-Closing');
                    });
            })->count();

        // Count plans needing report (EXPIRED based on Threshold)
        // Frontend logic: Expired if (Now - PlanningDate) > ExpiryThreshold
        // So PlanningDate < Now - ExpiryThreshold
        $expiryValue = $timeSettings?->plan_expiry_value ?? 7;
        $expiryUnit = $timeSettings?->plan_expiry_unit ?? 'Days';

        $expiryCutoff = TimeSetting::testingNow();

        if (str_contains(strtolower($expiryUnit), 'minute')) {
            $expiryCutoff->subMinutes((int) $expiryValue);
        } elseif (str_contains(strtolower($expiryUnit), 'hour')) {
            $expiryCutoff->subHours((int) $expiryValue);
        } else {
            $expiryCutoff->subDays((int) $expiryValue);
        }
        $expiryCutoffStr = $expiryCutoff->toDateTimeString();

        $plansNeedingReport = (clone $allCustomersQuery)
            ->whereHas('latestPlan', function ($q) use ($expiryCutoffStr) {
                $q->whereDoesntHave('report')
                    // FIX: Use created_at for expiry calculation, NOT planning_date
                    // This is consistent with the frontend logic fix and the Plan model's isExpired()
                    ->where('created_at', '<', $expiryCutoffStr);
            })->count();

        // Total Attention Needed (Expired + Rejected + Late)
        $totalAttention = $plansNeedingReport + $plansRejected + $plansLateFollowUp;

        // Reminder data for popup
        $reminderData = [
            'customersWithoutPlanning' => $customersWithoutPlanning,
            'plansNeedingReport' => $plansNeedingReport,
            'plansRejected' => $plansRejected,
            'plansLateFollowUp' => $plansLateFollowUp,
            'bod_review_count' => $bodReviewCount,
            'manager_approval_count' => $managerApprovalCount,
            'expired_count' => $totalAttention, // Consolidated count
            'missing_plans_count' => $customersWithoutPlanning,
            'customersWithoutPlanningList' => (clone $allCustomersQuery)
                ->whereDoesntHave('plans')
                ->limit(5)
                ->get(['id', 'company_name', 'product_id'])
                ->load('product:id,name'),
            'plansNeedingReportList' => (clone $allCustomersQuery)
                ->whereHas('latestPlan', function ($q) use ($expiryCutoffStr) {
                    $q->whereDoesntHave('report')
                        // FIX: Use created_at for consistency
                        ->where('created_at', '<', $expiryCutoffStr);
                })
                ->limit(5)
                ->get(['id', 'company_name'])
                ->load(['latestPlan']),
        ];

        $timeSettings = TimeSetting::first();

        return Inertia::render('Planning/Index', [
            'customers' => $paginatedCustomers,
            'user_roles' => Auth::user() ? Auth::user()->getRoleNames() : [],
            'timeSettings' => $timeSettings,
            'currentSimulatedTime' => TimeSetting::testingNow()->toISOString(),
            'reminderData' => $reminderData,
            'filters' => array_merge($request->only(['search', 'team', 'user', 'sort', 'direction', 'perPage', 'month', 'year']), ['tab' => $currentTab]),
            'teams' => Team::select('id', 'name', 'manager_id')
                ->with('manager:id,name')
                ->when($user->hasRole('Manager') && !$user->hasRole(['Super Admin', 'Board of Director']), function ($q) use ($user) {
                    $q->where('id', $user->team_id);
                })
                ->orderBy('name')
                ->get(),
            'users' => User::select('id', 'name', 'team_id')
                ->selectSub(function ($query) {
                    $query->select('id')
                        ->from('teams')
                        ->whereColumn('teams.manager_id', 'users.id')
                        ->limit(1);
                }, 'managed_team_id')
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['User', 'Supervisor', 'Manager']);
                })
                ->where(function ($q) {
                    $q->whereNotNull('team_id')
                        ->orWhereIn('id', function ($subQuery) {
                            $subQuery->select('manager_id')
                                ->from('teams')
                                ->whereNotNull('manager_id');
                        });
                })
                ->with('team:id,manager_id')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    if (!$user->team_id && $user->managed_team_id) {
                        $user->team_id = $user->managed_team_id;
                    }
                    $user->is_manager = ($user->team && $user->team->manager_id == $user->id)
                        || (!empty($user->managed_team_id));
                    return $user;
                }),
        ]);
    }


    public function create(Request $request)
    {
        // Strict Day Validation
        $settings = TimeSetting::first();
        if ($settings && !$settings->testing_mode) {
            $allowedDaysRaw = $settings->allowed_plan_creation_days ?? ['Friday']; // Default Friday

            // Map string days to integers if necessary
            $dayMap = [
                'Sunday' => 0,
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6
            ];

            $allowedDays = [];
            foreach ($allowedDaysRaw as $d) {
                if (is_numeric($d)) {
                    $allowedDays[] = (int) $d;
                } elseif (is_string($d) && isset($dayMap[$d])) {
                    $allowedDays[] = $dayMap[$d];
                }
            }

            // Carbon/PHP dayOfWeek: 0 (Sun) - 6 (Sat)
            if (!in_array(now()->dayOfWeek, $allowedDays)) {
                return redirect()->route('planning.index')->with('error', 'Plan creation is not allowed today.');
            }
        }

        $user = Auth::user();

        // Get customers based on user role
        $customersQuery = Customer::with('product')->orderBy('company_name');

        // Super Admin can see all customers
        if (!$user->hasRole('Super Admin')) {
            // Regular users only see their own customers
            $customersQuery->where('marketing_sales_id', $user->id);
        }

        // Check if customer_id is passed (from table row Create Plan button)
        $selectedCustomer = null;
        if ($request->has('customer')) {
            $selectedCustomer = Customer::with('product')->find($request->customer);
        }

        return Inertia::render('Planning/Create', [
            'customers' => $customersQuery->get(),
            'products' => Product::all(),
            'selectedCustomer' => $selectedCustomer,
        ]);
    }

    public function store(Request $request)
    {
        // Strict Day Validation
        $settings = TimeSetting::first();
        if ($settings && !$settings->testing_mode) {
            $allowedDaysRaw = $settings->allowed_plan_creation_days ?? ['Friday'];

            // Map string days to integers if necessary
            $dayMap = [
                'Sunday' => 0,
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6
            ];

            $allowedDays = [];
            foreach ($allowedDaysRaw as $d) {
                if (is_numeric($d)) {
                    $allowedDays[] = (int) $d;
                } elseif (is_string($d) && isset($dayMap[$d])) {
                    $allowedDays[] = $dayMap[$d];
                }
            }

            if (!in_array(now()->dayOfWeek, $allowedDays)) {
                return redirect()->route('planning.index')->with('error', 'Plan creation is not allowed today.');
            }
        }

        $request->validate([
            'planning_date' => 'required|date',
            'activity_type' => 'required|string',
            'customer_id' => 'required|exists:customers,id',
            'product_id' => 'nullable|exists:products,id',
            'project_name' => 'nullable|string',
            'description' => 'required|string',
            'manager_status' => 'nullable|string|in:pending,approved,rejected,escalated', // Added manager_status
        ]);

        // Manager status should be null for new plans (only set to pending when report is submitted)
        $managerStatus = $request->manager_status ?? null;
        $managerReviewedAt = ($managerStatus && $managerStatus !== 'pending') ? TimeSetting::testingNow() : null;

        Plan::create([
            'user_id' => Auth::id(),
            'customer_id' => $request->customer_id,
            'product_id' => $request->product_id, // If null, maybe fallback? But validation allows null if logic dictates.
            'planning_date' => $request->planning_date,
            'activity_type' => $request->activity_type,
            'project_name' => $request->project_name,
            'description' => $request->description,
            'status' => 'created',
            'manager_status' => $managerStatus, // null for new plans
            'manager_reviewed_at' => $managerReviewedAt, // Set timestamp if manager already acted
            'bod_status' => null, // Also null initially, set to pending when report submitted
        ]);

        // Different success message for ESCALATION plans
        $successMessage = ($request->activity_type === 'ESCALATION')
            ? 'Escalation applied successfully.'
            : 'Plan created successfully.';

        // ESCALATION plans should redirect to 'All Planning' tab since they don't appear in week tabs
        if ($request->activity_type === 'ESCALATION') {
            return redirect()->route('planning.index', ['tab' => 'all'])->with('success', $successMessage);
        }

        return redirect()->route('planning.index')->with('success', $successMessage);
    }

    public function createReport(Plan $plan)
    {
        return Inertia::render('Planning/CreateReport', [
            'plan' => $plan->load(['customer', 'product']),
            'currentSimulatedTime' => TimeSetting::testingNow()->format('Y-m-d'),
        ]);
    }

    public function storeReport(Request $request, Plan $plan)
    {
        // Check if this is a closing deal (100%-Closing)
        $isClosing = $request->progress === '100%-Closing';

        // Base validation rules
        $rules = [
            'execution_date' => 'required|date',
            'location' => 'required|string',
            'pic' => 'required|string',
            'position' => 'required|string',
            'result_description' => 'required|string',
            'next_plan_description' => 'nullable|string',
            'progress' => 'required|string',
            'is_success' => 'required|boolean',
        ];

        // Next plan fields are only required if NOT closing
        if (!$isClosing) {
            $rules['next_activity_type'] = 'required|string';
            $rules['next_planning_date'] = 'required|date';
        } else {
            $rules['next_activity_type'] = 'nullable|string';
            $rules['next_planning_date'] = 'nullable|date';
        }

        $request->validate($rules);

        $plan->report()->create($request->all());

        // Update plan status to reported AND set approval statuses to pending
        // Use simulated time for updated_at to ensure correct inactivity calculation
        $plan->update([
            'status' => 'reported',
            'manager_status' => 'pending', // Manager needs to approve this report
            'bod_status' => 'pending',     // BOD needs to review after manager approval
            'updated_at' => TimeSetting::testingNow(), // Use simulated time for accurate tracking
        ]);

        // Notify Super Admins that a new report has been submitted for review
        $user = Auth::user();
        $superAdmins = User::role('Super Admin')->get();
        foreach ($superAdmins as $admin) {
            $admin->notify(new PlanStatusChanged($plan, 'reported', $user->name));
        }

        // AUTOMATICALLY CREATE NEXT PLAN (ONLY if NOT closing deal)
        if (!$isClosing) {
            Plan::create([
                'user_id' => Auth::id(),
                'customer_id' => $plan->customer_id,
                'product_id' => $plan->product_id,
                'planning_date' => $request->next_planning_date,
                'activity_type' => $request->next_activity_type,
                'project_name' => $plan->project_name, // Carry over project name
                'description' => $request->next_plan_description ?? 'Follow up from previous report',
                'status' => 'created',
                'manager_status' => null, // Will be set to pending when report is submitted
                'bod_status' => null,     // Will be set to pending when report is submitted
            ]);

            return redirect()->route('planning.index')->with('success', 'Report created and Next Plan automatically generated.');
        }

        // For closing deals, just return success without creating next plan
        return redirect()->route('planning.index')->with('success', 'Report submitted. Deal marked for closing - awaiting approval.');
    }

    /**
     * Get status change info for a plan (remaining changes, grace period, etc.)
     */
    public function getStatusChangeInfo(Plan $plan)
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $managerRemaining = $isSuperAdmin ? 999 : PlanStatusLog::getRemainingChanges($plan->id, 'manager_status');
        $bodRemaining = $isSuperAdmin ? 999 : PlanStatusLog::getRemainingChanges($plan->id, 'bod_status');

        $managerGraceTime = PlanStatusLog::getGraceTimeRemaining($plan->id, 'manager_status');
        $bodGraceTime = PlanStatusLog::getGraceTimeRemaining($plan->id, 'bod_status');

        return response()->json([
            'manager' => [
                'remaining' => $managerRemaining,
                'max' => PlanStatusLog::MAX_CHANGES_LIMIT,
                'can_change' => $isSuperAdmin || $managerRemaining > 0,
                'grace_seconds' => $managerGraceTime,
                'is_super_admin' => $isSuperAdmin,
            ],
            'bod' => [
                'remaining' => $bodRemaining,
                'max' => PlanStatusLog::MAX_CHANGES_LIMIT,
                'can_change' => $isSuperAdmin || $bodRemaining > 0,
                'grace_seconds' => $bodGraceTime,
                'is_super_admin' => $isSuperAdmin,
            ],
        ]);
    }

    // Update Manager Control Status
    // Update Manager Control Status
    public function updateControl(Request $request, Plan $plan)
    {
        $request->validate([
            'manager_status' => 'required|in:pending,rejected,escalated,approved',
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $newStatus = $request->manager_status;
        $oldStatus = $plan->manager_status;
        $field = 'manager_status';

        // Skip if status is the same, UNLESS it is a re-escalation (reminder update)
        if ($newStatus === $oldStatus && $newStatus !== 'escalated') {
            return back()->with('info', 'Status unchanged.');
        }

        // Check rate limit (Super Admin bypasses this)
        if (!$isSuperAdmin) {
            // Lock Check
            if (!$plan->canManagerChange($user)) {
                return back()->with('error', 'Cannot update status: Plan is locked (BOD finalized or grace period expired).');
            }

            // PROFESSIONAL WORKFLOW: Strict Lock
            // Once a decision is made (Approve/Reject), it is FINAL.
            // Manager cannot change it again. Only Super Admin can Reset.
            if ($oldStatus !== 'pending' && $oldStatus !== 'escalated') {
                // Allow changing 'escalated' because it's a temporary/reminder state.
                // But block changing Approved/Rejected.
                return back()->with('error', 'Decision is final. Contact Super Admin to reset if this was a mistake.');
            }
        }

        // Update the plan status
        // Always update updated_at with simulated time for accurate frontend calculations
        $updateData = [
            'manager_status' => $newStatus,
            'updated_at' => TimeSetting::testingNow(), // Use simulated time
        ];

        // Note: manager_reviewed_at is now set by PlanObserver::saving() using TimeSetting::testingNow()
        // No need to set it here as the Observer handles it when manager_status changes

        // AUTO-PROPAGATION: If Manager Rejects -> BOD Failed automatically
        if ($newStatus === 'rejected') {
            $updateData['bod_status'] = 'failed';
            $updateData['lifecycle_status'] = 'failed';
            $this->checkAndDeleteNextPlan($plan);
        }
        // RESET LOGIC: Explicit Reset to Pending (Super Admin Only / Reset Button)
        elseif ($newStatus === 'pending') {
            $updateData['bod_status'] = 'pending';
            $updateData['bod_reviewed_at'] = null; // Clear timestamp to unlock
            $updateData['lifecycle_status'] = 'active'; // Reset lifecycle

            // Hard Reset Quota: Delete logs for strict "Fresh Start"
            // This ensures Manager gets 3 new chances after a Reset.
            PlanStatusLog::where('plan_id', $plan->id)->where('field', 'manager_status')->delete();
        }
        // AUTO-RECOVERY: If Manager changes from Rejected -> Approved/Escalated, Reset BOD Failure
        elseif (($newStatus === 'approved' || $newStatus === 'escalated') && $plan->bod_status === 'failed') {
            $updateData['bod_status'] = 'pending';
            // Reset lifecycle if it was failed
            if ($plan->lifecycle_status === 'failed') {
                $updateData['lifecycle_status'] = 'active';
            }
        }

        $plan->update($updateData);

        // NOTIFICATION LOGIC (Manager)
        if ($newStatus !== 'pending' && $plan->user && $plan->user_id !== $user->id) {
            $plan->user->notify(new PlanStatusChanged($plan, $newStatus, $user->name));
        }

        // Notify all Super Admins (they should see all status changes)
        if ($newStatus !== 'pending') {
            $superAdmins = User::role('Super Admin')->get();
            foreach ($superAdmins as $admin) {
                // Determine source label
                $source = $user->hasRole('Manager') ? ' (Manager)' : ' (Admin)';
                $admin->notify(new PlanStatusChanged($plan, $newStatus, $user->name . $source));
            }
        }

        // Log the change (always count it)
        PlanStatusLog::create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'field' => $field,
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'is_grace_period' => false, // Always count the change
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'Control status updated successfully.');
    }

    // Update BOD Monitoring Status
    public function updateMonitoring(Request $request, Plan $plan)
    {
        $request->validate([
            'bod_status' => 'required|in:pending,failed,success',
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $newStatus = $request->bod_status;
        $oldStatus = $plan->bod_status;
        $field = 'bod_status';

        // Skip if status is the same
        if ($newStatus === $oldStatus) {
            return back()->with('info', 'Status unchanged.');
        }

        // Check rate limit (Super Admin bypasses this)
        if (!$isSuperAdmin) {
            // Lock Check
            if (!$plan->canBODChange($user)) {
                return back()->with('error', 'Cannot update status: Locked (Manager not approved or grace period expired).');
            }

            $remaining = PlanStatusLog::getRemainingChanges($plan->id, $field);
            if ($remaining <= 0) {
                return back()->with('error', 'You have reached the maximum status change limit (3x). Contact Super Admin if needed.');
            }
        }

        // Update the plan status with timestamp and reviewer
        $updateData = [
            'bod_status' => $newStatus,
            'bod_reviewed_at' => TimeSetting::testingNow(),
            'bod_reviewer_id' => $user->id,
            'updated_at' => TimeSetting::testingNow(), // Ensure consistency
        ];

        // AUTO-PROPAGATION: If BOD Fails -> Lifecycle Failed
        if ($newStatus === 'failed') {
            $updateData['lifecycle_status'] = 'failed';
            $this->checkAndDeleteNextPlan($plan);
        }

        $plan->update($updateData);

        // NOTIFICATION LOGIC (BOD)
        if ($newStatus !== 'pending' && $plan->user && $plan->user_id !== $user->id) {
            // Map BOD status to generic status
            $notifStatus = $newStatus === 'success' ? 'approved' : 'rejected';
            $plan->user->notify(new PlanStatusChanged($plan, $notifStatus, $user->name . ' (BOD)'));
        }

        // Notify all Super Admins (they should see all BOD status changes)
        if ($newStatus !== 'pending') {
            $notifStatus = $newStatus === 'success' ? 'approved' : 'rejected';
            $superAdmins = User::role('Super Admin')->get();
            foreach ($superAdmins as $admin) {
                $admin->notify(new PlanStatusChanged($plan, $notifStatus, $user->name . ' (BOD)'));
            }
        }

        // Log the change (always count it)
        PlanStatusLog::create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'field' => $field,
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'is_grace_period' => false, // Always count the change
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'Monitoring status updated successfully.');
    }

    /**
     * Reset status change logs for a plan (Super Admin only)
     */
    public function resetStatusLogs(Plan $plan)
    {
        $user = Auth::user();

        if (!$user->hasRole('Super Admin')) {
            return back()->with('error', 'Only Super Admin can reset status logs.');
        }

        // Delete all status logs for this plan
        PlanStatusLog::where('plan_id', $plan->id)->delete();

        return back()->with('success', 'Status change limits have been reset for this plan.');
    }

    /**
     * Reset all status change logs (Super Admin only)
     */
    public function resetAllStatusLogs()
    {
        $user = Auth::user();

        if (!$user->hasRole('Super Admin')) {
            return back()->with('error', 'Only Super Admin can reset status logs.');
        }

        // Delete all status logs
        PlanStatusLog::truncate();

        return back()->with('success', 'All status change limits have been reset.');
    }

    /**
     * Mark a plan as failed (User acknowledgement of failure/expiry)
     */
    public function markAsFailed(Plan $plan)
    {
        // Only allow if plan is strictly expired (and not already finalized as success)
        // Manual Fail - Removing strict isExpired check to prevent "stuck" states
        // if (!$plan->isExpired()) {
        //    return back()->with('error', 'Plan is not expired yet.');
        // }

        // Lock it down
        $plan->update([
            'manager_status' => 'rejected', // Auto-reject
            'bod_status' => 'failed',       // Auto-fail
            'lifecycle_status' => 'failed', // Final Failed
            // We append a note or just leave it
        ]);

        $this->checkAndDeleteNextPlan($plan);

        // Keep description intact, just status update

        return back()->with('success', 'Plan marked as failed. You can now create a new plan.');
    }

    /**
     * Handle Plan Revision (Combined Plan + Report Edit)
     */
    public function revise(Request $request, Plan $plan)
    {
        // Check if this is a closing deal (100%-Closing)
        $isClosing = $request->progress === '100%-Closing';

        // 1. Validate Base Plan
        $rules = [
            'planning_date' => 'required|date',
            'activity_type' => 'required|string',
            'description' => 'required|string|max:1000',
            'has_report' => 'boolean',
        ];

        // 2. Conditional Validation for Report & Next Plan
        if ($request->boolean('has_report')) {
            $rules['execution_date'] = 'required|date';
            $rules['result_description'] = 'required|string|max:1000';

            // Additional Report Fields (Matching CreateReport)
            $rules['location'] = 'required|string';
            $rules['pic'] = 'required|string';
            $rules['position'] = 'required|string';
            $rules['progress'] = 'required|string';
            $rules['is_success'] = 'boolean';

            // NEXT PLAN FIELDS - Only required if NOT closing
            if (!$isClosing) {
                $rules['next_planning_date'] = 'required|date|after_or_equal:execution_date';
                $rules['next_plan_description'] = 'nullable|string';
                $rules['next_activity_type'] = 'required|string';
            } else {
                $rules['next_planning_date'] = 'nullable|date';
                $rules['next_plan_description'] = 'nullable|string';
                $rules['next_activity_type'] = 'nullable|string';
            }
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $plan, $isClosing) {
            // A. Update Plan Details
            $plan->update([
                'planning_date' => $request->planning_date,
                'activity_type' => $request->activity_type,
                'description' => $request->description,
                'manager_status' => 'pending', // RESET TO PENDING
                'bod_status' => 'pending',
                'lifecycle_status' => 'active',
                'updated_at' => TimeSetting::testingNow(),
            ]);

            // B. Handle Report Logic
            if ($request->boolean('has_report')) {
                // Update or Create Report
                $reportData = [
                    'execution_date' => $request->execution_date,
                    'result_description' => $request->result_description,
                    'location' => $request->location,
                    'pic' => $request->pic,
                    'position' => $request->position,
                    'progress' => $request->progress,
                    'is_success' => $request->boolean('is_success'),
                    'is_late' => Carbon::parse($request->execution_date)->gt($plan->planning_date),
                    'next_plan_description' => $request->next_plan_description,
                    'next_plan_date' => $request->next_planning_date,
                    'next_activity_type' => $request->next_activity_type,
                ];

                $report = $plan->report()->updateOrCreate([], $reportData);

                // C. CREATE NEXT PLAN (Only if reporting AND NOT closing)
                if (!$isClosing) {
                    Plan::create([
                        'customer_id' => $plan->customer_id,
                        'user_id' => $plan->user_id,
                        'product_id' => $plan->product_id,
                        'project_name' => $plan->project_name,
                        'planning_date' => $request->next_planning_date,
                        'description' => $request->next_plan_description ?? 'Follow up from revision',
                        'activity_type' => $request->next_activity_type,
                        'status' => 'created',
                        'manager_status' => null, // Set to null/pending
                        'bod_status' => null,
                        'lifecycle_status' => 'active',
                        'created_at' => TimeSetting::testingNow(),
                        'updated_at' => TimeSetting::testingNow(),
                    ]);
                }

                // Update Plan status to reported
                $plan->update(['status' => 'reported']);

            } else {
                // User decided to REVISE PL AN ONLY (No Report)
                // We must PRESERVE the Old Plan (Rejected/Failed) in History.
                // So instead of Updating, we CREATE NEW PLAN (Clone with updates).

                $newPlan = Plan::create([
                    'customer_id' => $plan->customer_id,
                    'user_id' => Auth::id(), // The reviser becomes the owner? Or keep original? Usually Auth.
                    'product_id' => $plan->product_id,
                    'project_name' => $plan->project_name,
                    'planning_date' => $request->planning_date,
                    'activity_type' => $request->activity_type,
                    'description' => $request->description,
                    'status' => 'created',
                    'manager_status' => 'pending',
                    'bod_status' => 'pending',
                    'lifecycle_status' => 'active',
                ]);

                // Log Creation for New Plan
                PlanStatusLog::create([
                    'plan_id' => $newPlan->id,
                    'user_id' => Auth::id(),
                    'field' => 'manager_status',
                    'old_value' => null,
                    'new_value' => 'pending',
                    'reason' => 'Revised from Plan #' . $plan->id,
                    'created_at' => TimeSetting::testingNow(),
                ]);

                // Do NOT delete any reports from old plan (it keeps its history status)
                // And do not delete orphaned next plans of the old plan? 
                // Actually, if we are branching off, the old plan is dead end. 
                // Any 'Next Plan' linked to Old Plan (if it existed) is now irrelevant?
                // But usually Rejected plans don't have Next Plans.

                // We might want to mark Old Plan as 'Revised'? 
                // But 'rejected' is fine.
            }

            // D. Log the Revision Activity (General)
            // If we created a new plan, maybe log on the old plan too?
            PlanStatusLog::create([
                'plan_id' => $plan->id,
                'user_id' => Auth::id(),
                'field' => 'revision',
                'old_value' => 'rejected',
                'new_value' => $request->boolean('has_report') ? 'reported' : 'revised', // Just for log
                'reason' => $request->boolean('has_report') ? 'Revised with Report' : 'Plan Revised to #' . ($newPlan->id ?? 'unknown'),
                'created_at' => TimeSetting::testingNow(),
            ]);
        });

        // Ensure legacy orphaned next plans are removed when user revises
        $this->checkAndDeleteNextPlan($plan);

        return back()->with('success', 'Revision submitted successfully. New Plan created.');
    }

    /**
     * Helper to auto-delete generated Next Plan if the parent plan is rejected/failed.
     * This forces the user to create a proper Revision instead.
     */
    private function checkAndDeleteNextPlan(Plan $plan)
    {
        \Illuminate\Support\Facades\Log::info('checkAndDeleteNextPlan triggered for Plan ID: ' . $plan->id);

        // Explicitly load report if missing
        if (!$plan->relationLoaded('report')) {
            $plan->load('report');
        }

        if ($plan->report) {
            $report = $plan->report;
            $targetDate = $report->next_planning_date;
            \Illuminate\Support\Facades\Log::info('Report found. Next Planning Date: ' . ($targetDate ?? 'NULL'));

            // Query Builder for Next Plan
            $query = Plan::where('user_id', $plan->user_id)
                ->where('customer_id', $plan->customer_id)
                ->where('product_id', $plan->product_id)
                ->where('status', 'created');

            if ($targetDate) {
                // Scenario A: We have the explicit next date stored
                \Illuminate\Support\Facades\Log::info('Using Target Date strategy.');
                $query->whereDate('planning_date', $targetDate);
            } else {
                // Scenario B: Fallback (Legacy/Missing Data)
                // If next_planning_date was not saved, look for a plan created around the same time as the report.
                // Assuming Next Plan is created immediately after Report in the same request.
                \Illuminate\Support\Facades\Log::info('Using Fallback Time Window strategy.');
                $windowStart = $report->created_at->copy()->subMinutes(1);
                $windowEnd = $report->created_at->copy()->addMinutes(1);

                $query->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->where('id', '!=', $plan->id); // Ensure we don't pick self (though status differs)
            }

            $nextPlan = $query->first();

            if ($nextPlan) {
                \Illuminate\Support\Facades\Log::info('Next Plan found (ID: ' . $nextPlan->id . '). Deleting...');
                $nextPlan->delete();
            } else {
                \Illuminate\Support\Facades\Log::info('No Next Plan found to delete.');
            }
        } else {
            \Illuminate\Support\Facades\Log::info('No Report found for this plan.');
        }
    }
}
