# 14 — Roles & Permissions (RBAC)

Self-contained RBAC: `users` ↔ `roles` ↔ `permissions` (many-to-many both ways). Enforced by
policies + `EnsureRole` / Laravel `can:` middleware. **Default-deny**: no permission → no access.

## Roles (seeded)

| Slug | Name | Intent |
|---|---|---|
| `super_admin` | Super Admin | Everything, incl. settings, users, integrations, audit |
| `hr_manager` | HR Manager | Full hiring ops: jobs, candidates, interviews, reports, exports, pipeline |
| `recruiter` | Recruiter | Create jobs/invitations, view candidates & reports, move stages |
| `dept_manager` | Department Manager | View candidates/reports for **their** department only |
| `viewer` | Interviewer Viewer | Read-only: reports, transcripts, replay |

## Permission catalog (`permissions.slug`, grouped)

| Group | Permissions |
|---|---|
| jobs | `job.view`, `job.create`, `job.update`, `job.delete` |
| invitations | `invitation.create`, `invitation.cancel` |
| candidates | `candidate.view`, `candidate.update`, `candidate.delete`, `gdpr.erase` |
| interviews | `interview.view`, `interview.monitor`, `interview.move_stage` |
| reports | `report.view`, `report.export` |
| config | `template.manage`, `avatar.manage`, `question.manage`, `pipeline.manage` |
| users | `user.manage` |
| settings | `settings.manage`, `integration.manage` |
| audit | `audit.view` |

## Permission matrix

| Permission | super_admin | hr_manager | recruiter | dept_manager | viewer |
|---|:--:|:--:|:--:|:--:|:--:|
| job.view | ✅ | ✅ | ✅ | ✅¹ | ✅¹ |
| job.create / update | ✅ | ✅ | ✅ | — | — |
| job.delete | ✅ | ✅ | — | — | — |
| invitation.create / cancel | ✅ | ✅ | ✅ | — | — |
| candidate.view | ✅ | ✅ | ✅ | ✅¹ | ✅¹ |
| candidate.update | ✅ | ✅ | ✅ | — | — |
| candidate.delete | ✅ | ✅ | — | — | — |
| gdpr.erase | ✅ | ✅ | — | — | — |
| interview.view | ✅ | ✅ | ✅ | ✅¹ | ✅¹ |
| interview.monitor (live) | ✅ | ✅ | ✅ | — | — |
| interview.move_stage | ✅ | ✅ | ✅ | ✅¹ | — |
| report.view | ✅ | ✅ | ✅ | ✅¹ | ✅¹ |
| report.export | ✅ | ✅ | ✅ | ✅¹ | — |
| template.manage | ✅ | ✅ | — | — | — |
| avatar.manage | ✅ | ✅ | — | — | — |
| question.manage | ✅ | ✅ | ✅ | — | — |
| pipeline.manage | ✅ | ✅ | — | — | — |
| user.manage | ✅ | — | — | — | — |
| settings.manage / integration.manage | ✅ | — | — | — | — |
| audit.view | ✅ | ✅ | — | — | — |

¹ **Department scoping**: `dept_manager` and `viewer` are additionally constrained by a global
query scope to rows whose `job_positions.department_id` matches the user's department(s). This is
enforced in policies + an Eloquent global scope, not just the UI.

## Enforcement points

1. **Route middleware** — `->middleware('can:job.create')` or `EnsureRole:hr_manager,super_admin`.
2. **Policies** — `InterviewPolicy`, `JobPositionPolicy`, `CandidatePolicy`, etc. gate
   view/update/delete and apply department scoping.
3. **Global scopes** — `DepartmentScope` auto-filters queries for department-scoped roles.
4. **Blade** — `@can` directives hide unauthorized UI (defense in depth, not the control).

## Seeding

`RolePermissionSeeder` creates the roles, the full permission catalog, and the matrix above.
`DemoSeeder` creates one user per role (e.g. `admin@watad.com` / `password`) for local dev.

## Example (route protection)

```php
Route::middleware(['auth','can:report.export'])
    ->get('/api/export/interviews.xlsx', [ExportController::class, 'interviews']);

Route::middleware(['auth','can:user.manage'])
    ->apiResource('users', UserController::class);
```
