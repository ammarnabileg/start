<?php
// views/hr/candidates/compare.php
// Variables: $candidates (array of candidate+skill+personality+recommendation data),
//            $comparison (array, optional metadata)
$candidates = $candidates ?? [];
$comparison = $comparison ?? [];

$skillKeys = [
    'technical_competency' => 'Technical Competency',
    'communication'        => 'Communication',
    'problem_solving'      => 'Problem Solving',
    'critical_thinking'    => 'Critical Thinking',
    'confidence'           => 'Confidence',
    'leadership'           => 'Leadership',
    'culture_fit'          => 'Culture Fit',
    'professionalism'      => 'Professionalism',
    'ai_knowledge'         => 'AI Knowledge',
    'english_proficiency'  => 'English Proficiency',
    'learning_ability'     => 'Learning Ability',
];

function compScore(?float $s, array $all, string $key): string {
    if ($s === null) return '<span style="color:#475569;">—</span>';
    $vals = array_filter(array_map(fn($c) => isset($c['skills'][$key]) ? (float)$c['skills'][$key] : null, $all), fn($v) => $v !== null);
    $max = $vals ? max($vals) : 0;
    $isTop = $vals && count($vals) > 1 && abs($s - $max) < 0.01;
    $color = $s >= 80 ? '#4ade80' : ($s >= 60 ? '#fbbf24' : '#f87171');
    $star = $isTop ? ' <svg width="10" height="10" fill="#fbbf24" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' : '';
    return "<span style=\"font-weight:700;color:{$color};\">" . number_format($s, 1) . "</span>{$star}";
}
function compRec(?string $r): string {
    if (!$r) return '<span style="color:#475569;">—</span>';
    $map = ['strong_yes'=>['#4ade80','Strong Yes'],'yes'=>['#86efac','Yes'],'maybe'=>['#fbbf24','Maybe'],'no'=>['#f87171','No']];
    [$c,$l] = $map[$r] ?? ['#94a3b8', ucfirst((string)$r)];
    return "<span style=\"font-weight:700;color:{$c}\">{$l}</span>";
}
function discType(array $p): string {
    $scores = ['D'=>(float)($p['disc_d']??0),'I'=>(float)($p['disc_i']??0),'S'=>(float)($p['disc_s']??0),'C'=>(float)($p['disc_c']??0)];
    if (!array_filter($scores)) return '<span style="color:#475569;">—</span>';
    arsort($scores);
    $top = array_key_first($scores);
    $colors = ['D'=>'#f87171','I'=>'#fbbf24','S'=>'#4ade80','C'=>'#818cf8'];
    $c = $colors[$top] ?? '#94a3b8';
    return "<span style=\"font-weight:700;color:{$c}\">{$top} ({$top}" . number_format($scores[$top], 0) . ")</span>";
}
function topSkill(array $skills): string {
    if (empty($skills)) return '—';
    arsort($skills);
    $key = array_key_first($skills);
    $labels = ['technical_competency'=>'Technical','communication'=>'Communication','problem_solving'=>'Problem Solving','critical_thinking'=>'Critical Thinking','confidence'=>'Confidence','leadership'=>'Leadership','culture_fit'=>'Culture Fit','professionalism'=>'Professionalism','ai_knowledge'=>'AI Knowledge','english_proficiency'=>'English','learning_ability'=>'Learning'];
    return htmlspecialchars($labels[$key] ?? ucwords(str_replace('_',' ',$key)));
}

$numCandidates = count($candidates);
$colWidth = $numCandidates > 0 ? round(100 / $numCandidates) . '%' : '20%';
?>
<style>
  .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px; }
  .page-title { font-size:1.5rem;font-weight:800;color:#f1f5f9; }
  .page-subtitle { color:#64748b;font-size:0.875rem;margin-top:2px; }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9; }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.78rem; }
  /* Comparison table */
  .compare-card { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;overflow:hidden;margin-bottom:20px; }
  .compare-table { width:100%;border-collapse:collapse; }
  .compare-table th, .compare-table td { padding:13px 16px;border-bottom:1px solid rgba(79,70,229,0.06); }
  .compare-table tr:last-child td { border-bottom:none; }
  .compare-table thead th { font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;background:rgba(15,15,26,0.5); }
  .compare-table tbody .row-label { font-size:0.82rem;font-weight:600;color:#64748b;white-space:nowrap;width:180px; }
  .compare-table tbody td { font-size:0.875rem;color:#cbd5e1;text-align:center;vertical-align:middle; }
  .compare-table tbody td:first-child { text-align:left; }
  .compare-table tr:hover td { background:rgba(79,70,229,0.03); }
  .section-separator td { background:rgba(79,70,229,0.06) !important;padding:8px 16px !important;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#4f46e5 !important;border-top:1px solid rgba(79,70,229,0.15) !important; }
  .initials-sm { width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;margin:0 auto 8px; }
  .cand-header-name { font-weight:700;color:#e2e8f0;font-size:0.9rem; }
  .cand-header-job { font-size:0.75rem;color:#64748b;margin-top:3px; }
  .score-bar { height:5px;background:rgba(79,70,229,0.12);border-radius:3px;overflow:hidden;margin-top:4px; }
  .score-fill { height:100%;border-radius:3px; }
  .badge-cell { font-size:0.75rem; }
  /* AI chat */
  .ai-chat-panel {
    background:#1e1e32;border:1px solid rgba(79,70,229,0.2);border-radius:14px;overflow:hidden;
    margin-top:20px;
  }
  .ai-chat-header { padding:14px 20px;border-bottom:1px solid rgba(79,70,229,0.1);display:flex;align-items:center;gap:10px; }
  .ai-chat-title { font-size:0.9rem;font-weight:700;color:#e2e8f0; }
  .ai-chat-subtitle { font-size:0.78rem;color:#64748b;margin-top:1px; }
  .ai-messages { min-height:200px;max-height:360px;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:12px; }
  .ai-msg { display:flex;gap:10px; }
  .ai-msg.user { flex-direction:row-reverse; }
  .ai-msg-avatar { width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;color:#fff;flex-shrink:0;align-self:flex-end; }
  .ai-msg-bubble { max-width:80%;padding:10px 14px;border-radius:12px;font-size:0.875rem;line-height:1.6;color:#e2e8f0;background:rgba(79,70,229,0.1);border:1px solid rgba(79,70,229,0.15); }
  .ai-msg.user .ai-msg-bubble { background:rgba(15,15,26,0.6);border-color:rgba(100,116,139,0.2); }
  .ai-chat-input { padding:14px 16px;border-top:1px solid rgba(79,70,229,0.1);display:flex;gap:10px;align-items:flex-end; }
  .ai-chat-input textarea { flex:1;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:10px;color:#e2e8f0;padding:10px 14px;font-size:0.875rem;font-family:inherit;resize:none;outline:none;min-height:42px;max-height:120px;line-height:1.5; }
  .ai-chat-input textarea:focus { border-color:#4f46e5;box-shadow:0 0 0 2px rgba(79,70,229,0.1); }
  .ai-send-btn { width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .ai-send-btn:hover { opacity:0.9; }
  .ai-send-btn svg { color:#fff; }
  .ai-thinking { display:flex;gap:4px;align-items:center;padding:10px 14px; }
  .ai-thinking span { width:6px;height:6px;border-radius:50%;background:#818cf8;animation:bounce 1.2s infinite; }
  .ai-thinking span:nth-child(2) { animation-delay:0.2s; }
  .ai-thinking span:nth-child(3) { animation-delay:0.4s; }
  @keyframes bounce { 0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)} }
  .empty-compare { text-align:center;padding:64px;color:#475569; }
  .empty-compare h3 { color:#94a3b8;margin-bottom:8px; }
  .back-btn-row { display:flex;gap:8px;margin-bottom:20px; }
</style>

<div class="page-header">
  <div>
    <div class="page-title">Candidate Comparison</div>
    <div class="page-subtitle">Side-by-side comparison of <?= $numCandidates ?> candidate<?= $numCandidates !== 1 ? 's' : '' ?></div>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/candidates" class="btn btn-ghost btn-sm">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Candidates
    </a>
    <a href="?export=pdf<?= !empty($_GET['ids']) ? '&ids=' . htmlspecialchars($_GET['ids']) : '' ?>" class="btn btn-ghost btn-sm">Export PDF</a>
  </div>
</div>

<?php if (empty($candidates)): ?>
  <div class="empty-compare">
    <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 16px;display:block;opacity:0.3;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <h3>No Candidates Selected</h3>
    <p>Select 2–5 candidates from the candidates list to compare them side by side.</p>
    <a href="/candidates" class="btn btn-primary" style="margin-top:16px;">Select Candidates</a>
  </div>
<?php else: ?>
<div class="compare-card">
  <div style="overflow-x:auto;">
  <table class="compare-table">
    <thead>
      <tr>
        <th style="width:180px;text-align:left;">Metric</th>
        <?php foreach ($candidates as $c): ?>
          <?php
            $fn = $c['first_name'] ?? 'Unknown';
            $ln = $c['last_name'] ?? '';
            $initials = strtoupper(substr($fn,0,1) . substr($ln,0,1));
          ?>
          <th style="width:<?= $colWidth ?>;">
            <div style="text-align:center;">
              <div class="initials-sm"><?= htmlspecialchars($initials) ?></div>
              <div class="cand-header-name"><?= htmlspecialchars(trim("$fn $ln")) ?></div>
              <div class="cand-header-job"><?= htmlspecialchars($c['job_title'] ?? '') ?></div>
            </div>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <!-- Overall -->
      <tr class="section-separator"><td colspan="<?= $numCandidates + 1 ?>">Overall Assessment</td></tr>
      <tr>
        <td class="row-label">AI Score</td>
        <?php foreach ($candidates as $c):
          $score = isset($c['recommendation']['final_score']) ? (float)$c['recommendation']['final_score'] : null;
          $scores = array_filter(array_map(fn($cx) => isset($cx['recommendation']['final_score']) ? (float)$cx['recommendation']['final_score'] : null, $candidates));
          $max = $scores ? max($scores) : 0;
          $isTop = $score !== null && $scores && count($scores) > 1 && abs($score - $max) < 0.01;
          $color = $score >= 80 ? '#4ade80' : ($score >= 60 ? '#fbbf24' : '#f87171');
        ?>
          <td>
            <?php if ($score !== null): ?>
              <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;line-height:1;"><?= number_format($score, 0) ?><?= $isTop ? '<svg width="14" height="14" fill="#fbbf24" viewBox="0 0 24 24" style="vertical-align:-2px;margin-left:3px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' : '' ?></div>
              <div class="score-bar"><div class="score-fill" style="width:<?= $score ?>%;background:<?= $color ?>;"></div></div>
            <?php else: echo '<span style="color:#475569;">—</span>'; endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">Recommendation</td>
        <?php foreach ($candidates as $c): ?>
          <td><?= compRec($c['recommendation']['recommendation'] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">Current Status</td>
        <?php foreach ($candidates as $c): ?>
          <td class="badge-cell">
            <?php
              $st = $c['status'] ?? 'applied';
              $stColors = ['applied'=>'#60a5fa','ai_screening'=>'#a78bfa','qualified'=>'#4ade80','disqualified'=>'#f87171','tech_interview'=>'#fbbf24','manager_interview'=>'#fb923c','final_review'=>'#f472b6','offer'=>'#2dd4bf','hired'=>'#4ade80','rejected'=>'#f87171','withdrawn'=>'#94a3b8'];
              $stc = $stColors[$st] ?? '#94a3b8';
            ?>
            <span style="background:<?= $stc ?>22;color:<?= $stc ?>;padding:3px 8px;border-radius:12px;font-size:0.72rem;font-weight:600;"><?= ucwords(str_replace('_',' ',$st)) ?></span>
          </td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">Top Skill</td>
        <?php foreach ($candidates as $c): ?>
          <td><?= topSkill($c['skills'] ?? []) ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Skills -->
      <tr class="section-separator"><td colspan="<?= $numCandidates + 1 ?>">Skill Scores</td></tr>
      <?php foreach ($skillKeys as $key => $label): ?>
        <tr>
          <td class="row-label"><?= $label ?></td>
          <?php foreach ($candidates as $c):
            $val = isset($c['skills'][$key]) ? (float)$c['skills'][$key] : null;
            $allVals = array_filter(array_map(fn($cx) => isset($cx['skills'][$key]) ? (float)$cx['skills'][$key] : null, $candidates), fn($v) => $v !== null);
            $max = $allVals ? max($allVals) : 0;
            $isTop = $val !== null && $allVals && count($allVals) > 1 && abs($val - $max) < 0.01;
            $color = $val === null ? '#475569' : ($val >= 80 ? '#4ade80' : ($val >= 60 ? '#fbbf24' : '#f87171'));
          ?>
            <td>
              <?php if ($val !== null): ?>
                <div style="font-weight:700;color:<?= $color ?>;font-size:0.9rem;"><?= number_format($val, 1) ?><?= $isTop ? ' <svg width="10" height="10" fill="#fbbf24" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' : '' ?></div>
                <div class="score-bar"><div class="score-fill" style="width:<?= $val ?>%;background:<?= $color ?>;"></div></div>
              <?php else: echo '<span style="color:#475569;">—</span>'; endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>

      <!-- Behavioral -->
      <tr class="section-separator"><td colspan="<?= $numCandidates + 1 ?>">Behavioral Profile</td></tr>
      <tr>
        <td class="row-label">DISC Type</td>
        <?php foreach ($candidates as $c): ?>
          <td><?= discType($c['personality'] ?? []) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">Growth Score</td>
        <?php foreach ($candidates as $c): ?>
          <td><?php $gs = isset($c['personality']['growth_score']) ? (float)$c['personality']['growth_score'] : null; echo $gs !== null ? '<span style="font-weight:700;color:#4ade80;">' . number_format($gs, 0) . '</span>' : '<span style="color:#475569;">—</span>'; ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">Pressure Score</td>
        <?php foreach ($candidates as $c): ?>
          <td><?php $ps = isset($c['personality']['pressure_score']) ? (float)$c['personality']['pressure_score'] : null; echo $ps !== null ? '<span style="font-weight:700;color:#fbbf24;">' . number_format($ps, 0) . '</span>' : '<span style="color:#475569;">—</span>'; ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Risk -->
      <tr class="section-separator"><td colspan="<?= $numCandidates + 1 ?>">Risk Indicators</td></tr>
      <tr>
        <td class="row-label">Red Flags</td>
        <?php foreach ($candidates as $c):
          $rf = $c['red_flags_count'] ?? count($c['red_flags'] ?? []);
          $rfColor = $rf === 0 ? '#4ade80' : ($rf <= 2 ? '#fbbf24' : '#f87171');
        ?>
          <td>
            <span style="font-weight:700;color:<?= $rfColor ?>;"><?= (int)$rf ?></span>
            <span style="font-size:0.75rem;color:#475569;margin-left:4px;"><?= (int)$rf === 0 ? 'None' : 'flag' . ((int)$rf > 1 ? 's' : '') ?></span>
          </td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <td class="row-label">CV Match</td>
        <?php foreach ($candidates as $c): ?>
          <td><?php $cm = isset($c['cv_match_score']) ? (float)$c['cv_match_score'] : null; echo $cm !== null ? '<span style="font-weight:700;color:#818cf8;">' . number_format($cm, 0) . '%</span>' : '<span style="color:#475569;">—</span>'; ?></td>
        <?php endforeach; ?>
      </tr>

      <!-- Actions row -->
      <tr>
        <td class="row-label">Quick Actions</td>
        <?php foreach ($candidates as $c):
          $appId = (int)($c['application_id'] ?? $c['id'] ?? 0);
        ?>
          <td>
            <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
              <a href="/candidates/<?= $appId ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">View Profile</a>
              <form method="POST" action="/talent-pool/add" style="width:100%;">
                <input type="hidden" name="application_id" value="<?= $appId ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">+ Talent Pool</button>
              </form>
            </div>
          </td>
        <?php endforeach; ?>
      </tr>
    </tbody>
  </table>
  </div>
</div>

<!-- AI Ask Panel -->
<div class="ai-chat-panel">
  <div class="ai-chat-header">
    <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <svg width="16" height="16" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div>
      <div class="ai-chat-title">Ask AI About These Candidates</div>
      <div class="ai-chat-subtitle">Ask questions and get AI-powered insights based on all candidate data</div>
    </div>
  </div>
  <div class="ai-messages" id="aiMessages">
    <div class="ai-msg">
      <div class="ai-msg-avatar">AI</div>
      <div class="ai-msg-bubble">
        Hi! I have access to all <?= $numCandidates ?> candidate profiles in this comparison. Ask me anything — like "Who would be best for a fast-paced startup?", "Which candidate has the strongest technical skills?", or "Compare their communication styles."
      </div>
    </div>
    <?php if (!empty($comparison['ai_messages'] ?? [])): ?>
      <?php foreach ($comparison['ai_messages'] as $msg): ?>
        <div class="ai-msg <?= $msg['role'] === 'user' ? 'user' : '' ?>">
          <div class="ai-msg-avatar"><?= $msg['role'] === 'user' ? 'You' : 'AI' ?></div>
          <div class="ai-msg-bubble"><?= htmlspecialchars($msg['content'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="ai-chat-input">
    <textarea id="aiQuestion" placeholder="Ask about these candidates… e.g. 'Who is more likely to be a good cultural fit?'" rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAiQuestion();}"></textarea>
    <button class="ai-send-btn" onclick="sendAiQuestion()">
      <svg width="16" height="16" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</div>
<?php endif; ?>

<script>
const candidateIds = <?= json_encode(array_map(fn($c) => (int)($c['application_id'] ?? $c['id'] ?? 0), $candidates)) ?>;

function sendAiQuestion() {
  const input = document.getElementById('aiQuestion');
  const question = input.value.trim();
  if (!question) return;
  const msgBox = document.getElementById('aiMessages');
  // Add user message
  msgBox.innerHTML += `
    <div class="ai-msg user">
      <div class="ai-msg-avatar">You</div>
      <div class="ai-msg-bubble">${escHtml(question)}</div>
    </div>`;
  input.value = '';
  // Show thinking
  const thinkId = 'think-' + Date.now();
  msgBox.innerHTML += `
    <div class="ai-msg" id="${thinkId}">
      <div class="ai-msg-avatar">AI</div>
      <div class="ai-msg-bubble"><div class="ai-thinking"><span></span><span></span><span></span></div></div>
    </div>`;
  msgBox.scrollTop = msgBox.scrollHeight;
  // API call
  fetch('/comparisons/ask', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ question, candidate_ids: candidateIds })
  })
    .then(r => r.json())
    .then(d => {
      document.getElementById(thinkId)?.remove();
      const answer = d.data?.answer || d.message || 'I could not generate an answer. Please try again.';
      msgBox.innerHTML += `
        <div class="ai-msg">
          <div class="ai-msg-avatar">AI</div>
          <div class="ai-msg-bubble">${escHtml(answer)}</div>
        </div>`;
      msgBox.scrollTop = msgBox.scrollHeight;
    })
    .catch(() => {
      document.getElementById(thinkId)?.remove();
      msgBox.innerHTML += `
        <div class="ai-msg">
          <div class="ai-msg-avatar">AI</div>
          <div class="ai-msg-bubble" style="color:#f87171;">Sorry, I could not connect to the AI service. Please try again.</div>
        </div>`;
      msgBox.scrollTop = msgBox.scrollHeight;
    });
}
function escHtml(t) {
  const d = document.createElement('div');
  d.textContent = t;
  return d.innerHTML;
}
// Auto-resize textarea
document.getElementById('aiQuestion')?.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
