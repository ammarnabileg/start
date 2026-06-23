<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::catalog() as $slug => $meta) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $meta['name'], 'group' => $meta['group']]);
        }

        $matrix = Permissions::matrix();

        foreach (RoleSlug::cases() as $roleSlug) {
            $role = Role::updateOrCreate(
                ['slug' => $roleSlug->value],
                ['name' => $roleSlug->label()],
            );

            $slugs = $matrix[$roleSlug->value] ?? [];
            $ids = ($slugs === ['*'])
                ? Permission::pluck('id')
                : Permission::whereIn('slug', $slugs)->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
