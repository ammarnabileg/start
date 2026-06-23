<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Granular permission control: the admin toggles view/create/update/delete per resource (plus
 * extra abilities) for each role. Super Admin always has full access and cannot be restricted.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('hr.roles', [
            'roles'     => Role::with('permissions')->orderBy('id')->get(),
            'resources' => Permissions::RESOURCES,
            'actions'   => Permissions::ACTIONS,
            'extra'     => Permissions::EXTRA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = Str::slug($data['name'], '_') ?: 'role';
        if (Role::where('slug', $slug)->exists()) {
            $slug .= '_'.Str::lower(Str::random(4));
        }

        Role::create(['slug' => $slug, 'name' => $data['name'], 'description' => $data['description'] ?? null]);

        return back()->with('status', "Role “{$data['name']}” created. Set its permissions below.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->slug === RoleSlug::SuperAdmin->value) {
            return back()->with('status', 'Super Admin always has full access and cannot be restricted.');
        }

        $slugs = array_values(array_intersect(
            (array) $request->input('permissions', []),
            Permissions::all(),
        ));

        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $role->permissions()->sync($ids);

        return back()->with('status', "Permissions updated for {$role->name}.");
    }
}
