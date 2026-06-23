<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $request->validate(['roles' => ['array'], 'roles.*' => ['exists:roles,id']]);
        $user->roles()->sync($request->input('roles', []));

        return back()->with('status', 'Roles updated.');
    }
}
