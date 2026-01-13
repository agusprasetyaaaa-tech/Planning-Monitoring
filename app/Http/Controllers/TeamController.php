<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $query = Team::select('id', 'name', 'manager_id', 'created_at')
            ->with(['manager:id,name'])->withCount('members');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhereHas('manager', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }

        if ($request->has(['sort', 'direction'])) {
            if ($request->sort === 'members_count') {
                $query->orderBy('members_count', $request->direction);
            } else {
                $query->orderBy($request->sort, $request->direction);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return Inertia::render('Teams/Index', [
            'teams' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create', [
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'manager_id' => 'required|exists:users,id',
        ]);

        Team::create($request->all());

        return redirect()->route('teams.index')->with('success', 'Team created successfully.');
    }

    public function edit(Team $team)
    {
        return Inertia::render('Teams/Edit', [
            'team' => $team,
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Team $team)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'manager_id' => 'required|exists:users,id',
        ]);

        $team->update($request->all());

        return redirect()->route('teams.index')->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Team deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:teams,id',
        ]);

        Team::whereIn('id', $request->ids)->delete();

        return redirect()->route('teams.index')->with('success', 'Teams deleted successfully.');
    }

    public function members(Team $team)
    {
        $team->load(['members', 'manager']);
        $availableUsers = User::whereNull('team_id')
            ->orWhere('team_id', $team->id)
            ->select('id', 'name', 'team_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Teams/Members', [
            'team' => $team,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function assignMember(Request $request, Team $team)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        User::where('id', $request->user_id)->update(['team_id' => $team->id]);

        return redirect()->route('teams.members', $team->id)->with('success', 'Member added successfully.');
    }

    public function removeMember(Team $team, User $user)
    {
        $user->update(['team_id' => null]);

        return redirect()->route('teams.members', $team->id)->with('success', 'Member removed successfully.');
    }
}
