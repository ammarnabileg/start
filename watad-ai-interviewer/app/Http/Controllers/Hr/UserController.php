<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('hr.users', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles'    => ['array'],
            'roles.*'  => ['exists:roles,id'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $user->roles()->sync($data['roles'] ?? []);

        return back()->with('status', "User “{$user->name}” created.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('status', "User “{$user->name}” updated.");
    }

    /** Activate / deactivate a user. Deactivated users keep their data but cannot sign in. */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $request->validate(['roles' => ['array'], 'roles.*' => ['exists:roles,id']]);
        $user->roles()->sync($request->input('roles', []));

        return back()->with('status', 'Roles updated.');
    }
}
