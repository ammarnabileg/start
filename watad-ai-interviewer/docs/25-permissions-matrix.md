# 25 — Dynamic Permissions Matrix

Every module exposes **uniform CRUD** (View / Create / Edit / Delete) plus **special abilities**.
Permissions are data (`permissions` table), assigned to roles (`permission_role`), and **fully
editable from the Admin Panel** (Roles & Permissions screen). Source of truth:
`app/Support/Permissions.php`. The **Super Admin always has full control** (Gate::before) and
admins can create **unlimited custom roles**.

## Resources (× view / create / update / delete)

`jobs` · `applications` · `candidates` · `ai_interviews` · `human_interviews` · `evaluations` ·
`offers` · `departments` · `pipelines` · `templates` · `avatars` · `questions` · `talent_pool` ·
`documents` · `notes` · `tags` · `users` · `roles` · `reports` · `settings` · `integrations` ·
`ai_config`

→ e.g. `candidates.view`, `candidates.create`, `candidates.update`, `candidates.delete`, …

## Special (non-CRUD) abilities

| Slug | Meaning |
|---|---|
| `invitations.create` | Generate AI interview links |
| `ai_interviews.monitor` | Watch live AI interviews |
| `interviews.schedule` | Schedule human interviews |
| `decisions.advance` | Advance candidate to next stage |
| `decisions.reject` | Reject a candidate |
| `decisions.override_ai` | Override the AI screening recommendation |
| `decisions.approve` | Final approval |
| `decisions.make_offer` | Create/extend an offer |
| `candidates.move_stage` | Move on the pipeline / Kanban |
| `candidates.access_financial` | See salary expectations & offer figures |
| `candidates.access_sensitive` | See sensitive PII (DOB, nationality, etc.) |
| `reports.export` | Export reports / data (Excel, Sheets) |
| `data.export` | Bulk data export / API export |
| `workflows.manage` | Configure pipelines & stage rules |
| `audit.view` | View audit logs |

## Roles (seeded; admin may add custom roles)

| Role | Intent |
|---|---|
| **Super Admin** | The HR Director. **Full control, locked** — every permission, always. |
| **HR Director** | Near-total: full hiring + decisions + users; not destructive platform ops |
| **HR Manager** | Full hiring ops + decisions + offers + reports; not user/role/system admin |
| **Recruiter** | Source, screen, advance/reject, schedule; no final approval/override |
| **Technical Interviewer** | Sees **assigned** candidates/interviews; submits evaluations only |
| **Department Manager** | Dept-scoped: view candidates, schedule, evaluate, advance/reject |
| **Operations Manager** | Like Department Manager, Operations scope |
| **Executive Reviewer** | Final-review authority: approve / reject / make offer; read-heavy |
| **Viewer** | Read-only across hiring data |
| **Custom roles** | Created by admin in the Roles UI; any permission combination |

## Capability matrix (summary)

✅ = granted · ◔ = scoped (own/assigned or department) · — = none.

| Capability | Super | Director | HR Mgr | Recruiter | Tech Intv | Dept Mgr | Ops Mgr | Exec | Viewer |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| View candidates | ✅ | ✅ | ✅ | ✅ | ◔ | ◔ | ◔ | ✅ | ✅ |
| Edit / delete candidates | ✅ | ✅ | ✅ | ✏️/— | — | — | — | — | — |
| Jobs CRUD | ✅ | ✅ | ✅ | C/E | — | view | view | view | view |
| AI interviews — view / monitor | ✅ | ✅ | ✅ | ✅ | ◔ | ◔ | ◔ | ✅ | view |
| Schedule human interviews | ✅ | ✅ | ✅ | ✅ | — | ◔ | ◔ | ✅ | — |
| Submit evaluations | ✅ | ✅ | ✅ | — | ◔ | ◔ | ◔ | ✅ | — |
| Advance / Reject | ✅ | ✅ | ✅ | ✅ | — | ◔ | ◔ | ✅ | — |
| **Override AI decision** | ✅ | ✅ | ✅ | — | — | — | — | ✅ | — |
| Final Approve / Make Offer | ✅ | ✅ | ✅ | — | — | — | — | ✅ | — |
| Offers CRUD | ✅ | ✅ | ✅ | view | — | — | — | C/E | — |
| Talent pool | ✅ | ✅ | ✅ | ✅ | — | view | view | view | — |
| Reports / export | ✅ | ✅ | ✅ | ✅ | — | view | view | ✅ | view |
| Access financial / sensitive | ✅ | ✅ | ✅ | — | — | — | — | ✅ | — |
| Departments | ✅ | ✅ | view | view | — | own | own | view | — |
| Team members (users) | ✅ | ✅ | — | — | — | — | — | — | — |
| Roles & permissions | ✅ | view | — | — | — | — | — | — | — |
| AI configuration | ✅ | ✅ | view | — | — | — | — | — | — |
| Workflows / pipelines | ✅ | ✅ | ✅ | view | — | — | — | — | — |
| System settings / integrations | ✅ | ✏️ | — | — | — | — | — | — | — |
| Audit logs | ✅ | ✅ | ✅ | — | — | — | — | view | — |

> The table is a **default seed**. The real, per-cell source of truth is the `permission_role`
> table, edited live in **Roles & Permissions** (`/hr/roles`) — rows = resources, columns =
> View/Create/Edit/Delete, plus special-ability checkboxes. Super Admin is shown checked & locked.

## Enforcement

- **Gate::before** → `super_admin` always allowed; any dotted ability resolves to `hasPermission`.
- **Route middleware** `can:{ability}` on every route.
- **Policies + global scopes** for record-level checks and ◔ scoping (department / assigned).
- **Field masking** for `candidates.access_financial` / `candidates.access_sensitive`.

See [`docs/14-rbac.md`](14-rbac.md) for mechanics and [`docs/26-schema-v2.md`](26-schema-v2.md) for
the `roles`/`permissions` tables.
