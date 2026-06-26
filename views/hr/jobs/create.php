<?php
// views/hr/jobs/create.php
// Variables: $departments (array), $avatars (array), $old (array), $errors (array), $job (array|null for edit mode)
$departments = $departments ?? [];
$avatars     = $avatars ?? [];
$old         = $old ?? [];
$errors      = $errors ?? [];
$job         = $job ?? null;
$isEdit      = $job !== null;
$formAction  = $isEdit ? '/jobs/' . (int)$job['id'] . '/edit' : '/jobs/create';
$pageTitle   = $isEdit ? 'Edit Job' : 'Create New Job';

function old(string $key, $default, array $old, ?array $job): string {
    if (isset($old[$key])) return htmlspecialchars((string)$old[$key]);
    if ($job && isset($job[$key])) return htmlspecialchars((string)$job[$key]);
    return htmlspecialchars((string)$default);
}
function oldSel(string $key, string $value, array $old, ?array $job): string {
    $cur = $old[$key] ?? ($job[$key] ?? '');
    return $cur === $value ? 'selected' : '';
}
function oldCheck(string $key, array $old, ?array $job): string {
    if (array_key_exists($key, $old)) return $old[$key] ? 'checked' : '';
    return ($job[$key] ?? false) ? 'checked' : '';
}
function fieldError(string $field, array $errors): string {
    if (isset($errors[$field])) {
        return '<div style="color:#f87171;font-size:0.78rem;margin-top:4px;">' . htmlspecialchars($errors[$field]) . '</div>';
    }
    return '';
}
?>
<style>
  .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px; }
  .page-title { font-size:1.6rem;font-weight:800;color:#f1f5f9; }
  .page-subtitle { color:#64748b;font-size:0.875rem;margin-top:2px; }
  .form-card { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:16px;padding:32px; }
  .form-section { margin-bottom:32px; }
  .form-section-title { font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#4f46e5;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid rgba(79,70,229,0.15); }
  .form-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px; }
  .form-group { display:flex;flex-direction:column;gap:6px; }
  .form-group.full { grid-column:1/-1; }
  .form-label { font-size:0.82rem;font-weight:600;color:#94a3b8;letter-spacing:0.02em; }
  .form-label .req { color:#f87171;margin-left:2px; }
  .form-input, .form-select, .form-textarea {
    width:100%;padding:10px 14px;
    background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;
    color:#e2e8f0;font-size:0.9rem;outline:none;
    transition:border-color 0.2s,box-shadow 0.2s;
    font-family:inherit;
  }
  .form-input:focus,.form-select:focus,.form-textarea:focus {
    border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,0.12);
  }
  .form-input::placeholder,.form-textarea::placeholder { color:#475569; }
  .form-select { cursor:pointer; }
  .form-select option { background:#1e1e32; }
  .form-textarea { resize:vertical;min-height:120px;line-height:1.6; }
  .salary-row { display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end; }
  .check-row { display:flex;align-items:center;gap:10px;margin-top:4px; }
  .check-row input[type=checkbox] { width:16px;height:16px;accent-color:#4f46e5;cursor:pointer; }
  .check-row label { font-size:0.875rem;color:#94a3b8;cursor:pointer; }
  .avatar-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px; }
  .avatar-option { position:relative; }
  .avatar-option input { position:absolute;opacity:0;width:0;height:0; }
  .avatar-card {
    border:2px solid rgba(79,70,229,0.15);border-radius:12px;padding:14px 12px;
    text-align:center;cursor:pointer;transition:all 0.2s;background:rgba(15,15,26,0.5);
  }
  .avatar-card:hover { border-color:rgba(79,70,229,0.4);background:rgba(79,70,229,0.05); }
  .avatar-option input:checked + .avatar-card { border-color:#4f46e5;background:rgba(79,70,229,0.1); }
  .avatar-img { width:52px;height:52px;border-radius:50%;margin:0 auto 8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden; }
  .avatar-img img { width:100%;height:100%;object-fit:cover; }
  .avatar-name { font-size:0.8rem;font-weight:600;color:#e2e8f0; }
  .avatar-style { font-size:0.72rem;color:#64748b;text-transform:capitalize;margin-top:2px; }
  .form-actions { display:flex;gap:12px;justify-content:flex-end;margin-top:32px;padding-top:24px;border-top:1px solid rgba(79,70,229,0.1); }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;font-size:0.9rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9;transform:translateY(-1px); }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .alert-error { background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:14px 18px;color:#fca5a5;font-size:0.875rem;margin-bottom:24px; }
  .breadcrumb { display:flex;align-items:center;gap:8px;font-size:0.82rem;color:#64748b;margin-bottom:20px; }
  .breadcrumb a { color:#64748b;text-decoration:none; }
  .breadcrumb a:hover { color:#818cf8; }
</style>

<div class="breadcrumb">
  <a href="/jobs">Jobs</a>
  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span style="color:#94a3b8;"><?= $isEdit ? 'Edit Job' : 'New Job' ?></span>
</div>

<div class="page-header">
  <div>
    <div class="page-title"><?= $pageTitle ?></div>
    <div class="page-subtitle"><?= $isEdit ? 'Update job details and requirements' : 'Post a new position and start receiving applications' ?></div>
  </div>
</div>

<?php if (!empty($errors) && isset($errors['_global'])): ?>
  <div class="alert-error"><?= htmlspecialchars($errors['_global']) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $formAction ?>" id="jobForm">

  <div class="form-card">

    <!-- Basic Info -->
    <div class="form-section">
      <div class="form-section-title">Basic Information</div>
      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label">Job Title <span class="req">*</span></label>
          <input type="text" name="title" class="form-input" value="<?= old('title', '', $old, $job) ?>" placeholder="e.g. Senior Software Engineer" required>
          <?= fieldError('title', $errors) ?>
        </div>
        <div class="form-group">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select">
            <option value="">No Department</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= (int)$dept['id'] ?>" <?= oldSel('department_id', (string)$dept['id'], $old, $job) ?>>
                <?= htmlspecialchars($dept['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Seniority Level <span class="req">*</span></label>
          <select name="seniority" class="form-select" required>
            <option value="">Select seniority…</option>
            <?php
            $seniorities = ['intern'=>'Intern','junior'=>'Junior','mid'=>'Mid-Level','senior'=>'Senior','lead'=>'Lead','manager'=>'Manager','director'=>'Director','executive'=>'Executive'];
            foreach ($seniorities as $val => $label): ?>
              <option value="<?= $val ?>" <?= oldSel('seniority', $val, $old, $job) ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <?= fieldError('seniority', $errors) ?>
        </div>
        <div class="form-group">
          <label class="form-label">Employment Type</label>
          <select name="employment_type" class="form-select">
            <?php
            $types = ['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','freelance'=>'Freelance','internship'=>'Internship'];
            foreach ($types as $val => $label): ?>
              <option value="<?= $val ?>" <?= oldSel('employment_type', $val, $old, $job) ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-input" value="<?= old('location', '', $old, $job) ?>" placeholder="e.g. Dubai, UAE">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="draft" <?= oldSel('status', 'draft', $old, $job) ?>>Draft</option>
            <option value="active" <?= oldSel('status', 'active', $old, $job) ?>>Active (Published)</option>
            <option value="paused" <?= oldSel('status', 'paused', $old, $job) ?>>Paused</option>
          </select>
        </div>
        <div class="form-group full">
          <div class="check-row">
            <input type="checkbox" name="is_remote" id="is_remote" value="1" <?= oldCheck('is_remote', $old, $job) ?>>
            <label for="is_remote">Remote work available</label>
          </div>
        </div>
      </div>
    </div>

    <!-- Compensation -->
    <div class="form-section">
      <div class="form-section-title">Compensation</div>
      <div class="salary-row">
        <div class="form-group">
          <label class="form-label">Salary Min</label>
          <input type="number" name="salary_min" class="form-input" value="<?= old('salary_min', '', $old, $job) ?>" placeholder="0" min="0" step="100">
        </div>
        <div class="form-group">
          <label class="form-label">Salary Max</label>
          <input type="number" name="salary_max" class="form-input" value="<?= old('salary_max', '', $old, $job) ?>" placeholder="0" min="0" step="100">
        </div>
        <div class="form-group">
          <label class="form-label">Currency</label>
          <select name="currency" class="form-select">
            <?php foreach (['USD','EUR','GBP','AED','SAR','QAR','KWD','BHD','OMR'] as $c): ?>
              <option value="<?= $c ?>" <?= oldSel('currency', $c, $old, $job) ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div class="form-section">
      <div class="form-section-title">Job Details</div>
      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label">Job Description <span class="req">*</span></label>
          <textarea name="description" class="form-textarea" rows="8" placeholder="Describe the role, responsibilities, and what makes this position great…" required><?= old('description', '', $old, $job) ?></textarea>
          <?= fieldError('description', $errors) ?>
        </div>
        <div class="form-group full">
          <label class="form-label">Requirements</label>
          <textarea name="requirements" class="form-textarea" rows="6" placeholder="List required skills, qualifications, and experience…"><?= old('requirements', '', $old, $job) ?></textarea>
        </div>
        <div class="form-group full">
          <label class="form-label">Benefits &amp; Perks</label>
          <textarea name="benefits" class="form-textarea" rows="4" placeholder="Health insurance, flexible hours, stock options…"><?= old('benefits', '', $old, $job) ?></textarea>
        </div>
      </div>
    </div>

    <!-- AI Avatar -->
    <?php if (!empty($avatars)): ?>
    <div class="form-section">
      <div class="form-section-title">AI Interview Avatar</div>
      <p style="color:#64748b;font-size:0.85rem;margin-bottom:16px;">Choose the AI avatar that will conduct candidate interviews for this job.</p>
      <div class="avatar-grid">
        <label class="avatar-option">
          <input type="radio" name="avatar_id" value="" <?= empty($old['avatar_id'] ?? $job['avatar_id'] ?? '') ? 'checked' : '' ?>>
          <div class="avatar-card">
            <div class="avatar-img">🤖</div>
            <div class="avatar-name">Default</div>
            <div class="avatar-style">Auto-select</div>
          </div>
        </label>
        <?php foreach ($avatars as $avatar): ?>
          <label class="avatar-option">
            <input type="radio" name="avatar_id" value="<?= (int)$avatar['id'] ?>"
              <?= oldSel('avatar_id', (string)$avatar['id'], $old, $job) ?>>
            <div class="avatar-card">
              <div class="avatar-img">
                <?php if (!empty($avatar['photo_url'])): ?>
                  <img src="<?= htmlspecialchars($avatar['photo_url']) ?>" alt="<?= htmlspecialchars($avatar['name']) ?>">
                <?php else: ?>
                  <?= strtoupper(substr($avatar['name'], 0, 2)) ?>
                <?php endif; ?>
              </div>
              <div class="avatar-name"><?= htmlspecialchars($avatar['name']) ?></div>
              <div class="avatar-style"><?= htmlspecialchars($avatar['style'] ?? '') ?> &middot; <?= htmlspecialchars($avatar['gender'] ?? '') ?></div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-actions">
      <a href="/jobs" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?= $isEdit ? 'Save Changes' : 'Create Job' ?>
      </button>
    </div>

  </div>
</form>
