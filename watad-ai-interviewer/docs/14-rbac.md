# 14 — Roles & Permissions (RBAC)

Self-contained, **granular CRUD** RBAC: every resource exposes **View / Create / Edit (update) /
Delete** permissions, plus a few special abilities. `users` ↔ `roles` ↔ `permissions` are
many-to-many. The **Super Admin has full control over everything** and cannot be restricted.
Permissions per role are editable from the admin UI (**Roles & Permissions** screen) and seeded by
the installer. Single source of truth: `app/Support/Permissions.php`.

## Resources (each gets view / create / update / delete)

`jobs` · `departments` · `candidates` · `interviews` · `templates` · `avatars` · `questions` ·
`pipelines` · `users` · `roles` · `reports` · `settings`

→ slugs like `jobs.view`, `jobs.create`, `jobs.update`, `jobs.delete`, … for all 12 resources
(48 CRUD permissions total).

## Special (non-CRUD) abilities

| Slug | Meaning |
|---|---|
| `invitations.create` | Generate candidate interview links |
| `interviews.monitor` | Watch live interviews |
| `interviews.move_stage` | Move a candidate across pipeline stages |
| `reports.export` | Export results (Excel / Sheets) |
| `candidates.erase` | GDPR erasure of candidate data |
| `integrations.manage` | Configure integrations |
| `audit.view` | View the audit log |

## Roles (seeded)

| Slug | Name | Intent |
|---|---|---|
| `super_admin` | Super Admin | **Everything** — full View/Create/Edit/Delete on all resources + all abilities. Locked. |
| `hr_manager` | HR Manager | All hiring resources (CRUD) + reports + audit; **not** user/role/settings administration |
| `recruiter` | Recruiter | Create/edit jobs, invite, view/update candidates, monitor & move interviews, view reports/export, manage questions |
| `dept_manager` | Department Manager | View jobs/candidates/interviews/reports + move stage (their department only) |
| `viewer` | Interviewer Viewer | Read-only: jobs, candidates, interviews, reports |

`dept_manager` and `viewer` are additionally constrained by a department scope (policy + global
scope) to rows whose `job_positions.department_id` matches the user's department(s).

## How it's enforced

1. **Gate::before** (in `AppServiceProvider`): `super_admin` is allowed everything; otherwise any
   dotted ability (e.g. `jobs.create`) resolves against the user's permissions
   (`User::hasPermission`). This means **new permissions work automatically** — no per-ability gate
   registration.
2. **Route middleware** — `->middleware('can:jobs.create')` on each route.
3. **Blade** — `@can('jobs.create')` hides unauthorized UI (defense in depth).
4. **Policies + global scope** — for record-level checks and department scoping.

## Managing permissions (admin UI)

`HR → Roles & Permissions` renders a live matrix per role: rows = resources, columns =
View/Create/Edit/Delete checkboxes, plus the special abilities. Toggling and saving syncs that
role's `permission_role` rows (`RoleController@update`). The Super Admin row is shown fully checked
and **locked** (it always has full access).

## Seeding & install

- `RolePermissionSeeder` creates the full catalog (`Permissions::catalog()`) and applies the role
  matrix (`Permissions::matrix()`).
- The **web installer** (`public/install.php`) runs this seeder and creates the first **Super
  Admin** account with the email/password you enter — that account then has full control and can
  configure every other role from the UI.

## Example (route protection)

```php
Route::middleware(['auth','can:reports.export'])
    ->get('/api/export/interviews.xlsx', [ExportController::class, 'interviews']);

Route::middleware(['auth','can:roles.update'])
    ->put('/hr/roles/{role}', [RoleController::class, 'update']);
```
