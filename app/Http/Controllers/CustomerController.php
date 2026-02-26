<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CustomersImport;
use App\Exports\CustomersTemplate;

class CustomerController extends Controller
{
    public function template()
    {
        return Excel::download(new CustomersTemplate, 'customers_template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:2048'
        ]);

        try {
            Excel::import(new CustomersImport, $request->file('file'));
            return redirect()->route('customers.index')->with('success', 'Customers imported successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:customers,id',
        ]);

        Customer::whereIn('id', $request->ids)->delete();

        return redirect()->route('customers.index')->with('success', 'Customers deleted successfully');
    }

    public function index(Request $request)
    {
        $query = Customer::select('id', 'company_name', 'product_id', 'marketing_sales_id', 'created_at', 'planning_start_date')
            ->with(['product:id,name', 'marketing:id,name']);

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
            $query->whereHas('marketing', function ($q) use ($request) {
                $q->where('team_id', $request->team)
                    ->orWhereHas('managedTeams', function ($mq) use ($request) {
                        $mq->where('id', $request->team);
                    });
            });
        }

        // Filter by user (marketing_sales_id)
        if ($request->user) {
            $query->where('marketing_sales_id', $request->user);
        }

        if ($request->has(['sort', 'direction'])) {
            $sort = $request->sort;
            $direction = $request->direction;

            if ($sort === 'product.name') {
                // Simplification: Order by created_at for now
                $query->orderBy('created_at', $direction);
            } elseif ($sort === 'marketing.name') {
                $query->orderBy('created_at', $direction);
            } else {
                $query->orderBy($sort, $direction);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all') {
            $perPage = $query->count() > 0 ? $query->count() : 10;
        }

        return Inertia::render('Customers/Index', [
            'customers' => $query->paginate((int) $perPage)->onEachSide(1)->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction', 'team', 'user', 'per_page']),
            'teams' => \App\Models\Team::select('id', 'name', 'manager_id')->with('manager:id,name')->orderBy('name')->get(),
            'users' => \App\Models\User::select('id', 'name', 'team_id')
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
                    // Include users with team_id OR users who are managers of a team
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
                    // Set team_id from managed_team_id if user is a manager without team_id
                    if (!$user->team_id && $user->managed_team_id) {
                        $user->team_id = $user->managed_team_id;
                    }
                    // Check if user is manager
                    $user->is_manager = ($user->team && $user->team->manager_id == $user->id)
                        || (!empty($user->managed_team_id));
                    return $user;
                }),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create', [
            'products' => Product::select('id', 'name')->get(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'marketing_sales_id' => 'required|exists:users,id',
            'planning_start_date' => 'nullable|date',
        ]);

        // Check for duplicate: same company_name with same product_id
        $exists = Customer::where('company_name', $request->company_name)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'company_name' => 'This customer already exists with the same product. You can add the same customer with a different product.'
            ])->withInput();
        }

        Customer::create($request->all());

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'products' => Product::select('id', 'name')->get(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'marketing_sales_id' => 'required|exists:users,id',
            'planning_start_date' => 'nullable|date',
        ]);

        // Check for duplicate: same company_name with same product_id (excluding current customer)
        $exists = Customer::where('company_name', $request->company_name)
            ->where('product_id', $request->product_id)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'company_name' => 'This customer already exists with the same product. You can add the same customer with a different product.'
            ])->withInput();
        }

        $customer->update($request->all());

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }


}
