# 29 — Wireframes v2 (Key Screens)

ASCII wireframes for the new multi-stage / portal screens. Style = the shared design system
([`docs/15`](15-wireframes-ui-ux.md)): light, blue accent, icon rail + section panel + top bar.

## Candidates list (`/hr/candidates`)
```
Candidates                                   [Import] [Compare] [＋ Candidate]
┌ Total 1284 ┐ ┌ In pipeline 240 ┐ ┌ Offers 12 ┐
[🔎 search name/email/skills] [Filter ▾ stage·job·score·tags·source] [Saved ▾] [Columns ▾]
☐ Name            Job                 Stage          AI    Reco       Tags        Owner   ⋯
☐ Mona Adel       Sr Backend Eng      Final Review   78    Qualified  #referral   M.A
☐ Omar Hassan     Sales Lead          Tech Interview 64    Borderline #senior     S.K
[ bulk: Tag · Talent pool · Move stage · Email · Reject · Export ]
```

## Candidate Master Profile (`/hr/candidates/{id}`)
```
‹ Back  Mona Adel    Final Review · AI Qualified(78)   [★Tag▾][⤴Talent pool][Compare][⋯]
[Overview][Applications][AI Interviews][Human Reviews][Documents][Notes][Timeline][Offers]
┌ Overview ─────────────────────────────────────────────────────────────┐
│ Contact: mona@… · +20… · linkedin/…     Exp 6y · Cairo · expects 55k EGP│
│ AI summary: strong system-design… (score bars)   Red flags: 1 (salary)  │
│ Latest: Sr Backend Eng — Final Review     Next: Director approval       │
└─────────────────────────────────────────────────────────────────────────┘
```

## Schedule human interview (`/hr/interviews` → New)
```
Schedule interview
Candidate [Mona Adel ▾]   Application [Sr Backend Eng ▾]
Type ( ) Technical (•) Manager ( ) Department ( ) Panel
Mode (•) Online ( ) Onsite     Provider [Zoom ▾]  (auto-generates link)
Date [2026-07-02] Time [14:00] TZ [Africa/Cairo] Duration [45m]
Panelists [+ add user]  ▸ Omar (Eng, lead) ▸ Sara (HR)
Message [editable invite]                          [Cancel] [Schedule & invite]
```

## Dynamic evaluation form (per job) (`/hr/interviews/{id}` → Evaluate)
```
Evaluation — Mona Adel · Technical Interview · template "Backend Engineer"
System design        ★★★★☆ (rating, weight 3)
Coding / DS&A        ★★★☆☆ (rating, weight 3)
Communication        ★★★★☆ (rating, weight 1)
Owns outcomes?       (•) Yes ( ) No        (boolean)
Seniority fit        [ Mid | Senior ▾ ]    (select)
Strengths  [＋ add]   Weaknesses [＋ add]
Notes [____________________________]
Overall ★★★★☆   Recommendation [Yes ▾]            [Save draft] [Submit]
```

## Hiring Pipeline — Kanban (`/hr/pipeline`)
```
[Job ▾][Dept ▾][Owner ▾]                                        swimlanes: off
Applied(40) │ AI Screening(18) │ Qualified(9) │ Tech IV(6) │ Mgr IV(4) │ Final(3) │ Offer(2) │ Hired │ Rej
┌──────────┐ ┌──────────┐      ┌──────────┐
│Mona  78  │ │Omar  64  │  ⇄drag│Lina  81  │ …
│#referral │ │2d in     │      │Qualified │
└──────────┘ └──────────┘      └──────────┘
```

## Offer (admin) (`/hr/offers/{id}`)
```
Offer — Mona Adel · Sr Backend Eng                          Status: Sent
Title [Senior Backend Engineer]  Salary [60000] [EGP]  Start [2026-08-01]
Expires [2026-07-15]      Letter: offer-XXXX.pdf [preview]
[Generate letter] [Send to candidate] [Withdraw]      Timeline: drafted→sent→viewed
```

## Portal — Dashboard (`/portal`)
```
Watad · Candidate                                              EN/AR  🌗  🔔  Mona ▾
Welcome back, Mona 👋
[Applied 3] [In review 1] [Upcoming IV 1] [Offers 1]
Upcoming: Manager Interview · Jul 2, 14:00 · [Join Zoom] [Add to calendar]
Action needed: ▸ Review your offer (Sr Backend Eng) → [Open]
Recent notifications ▸
```

## Portal — My Applications (`/portal/applications/{id}`)
```
‹ My applications   Senior Backend Engineer · Watad
●───●───●───○───○      Applied · AI Screening · Qualified · Interviews · Offer
Current: Interviews — "Manager interview scheduled for Jul 2"
What's next: attend the interview; we'll update you here.        [Withdraw]
```

## Portal — Offer + e-sign (`/portal/offers/{id}`)
```
Your offer — Senior Backend Engineer                         Expires in 6 days
[ PDF letter preview …………………………………………………… ]
Role: Senior Backend Engineer   Start: Aug 1   (salary as discussed)
☐ I have read and accept this offer
Signature: [ draw / type ]                         [Decline]  [Accept & sign]
```

## Roles & Permissions matrix (`/hr/roles`)
```
Roles & Permissions                                          [＋ New role]
Recruiter                                                    [Save]
Resource       View Create Edit Delete
Candidates      ☑    ☑     ☑    ☐
Jobs            ☑    ☑     ☑    ☐
Interviews      ☑    ☑     ☐    ☐
Offers          ☑    ☐     ☐    ☐         …
Special: ☑ Advance ☑ Reject ☐ Override AI ☐ Approve ☐ Make offer ☑ Export
Super Admin ……………………………………… Full control (locked)
```
