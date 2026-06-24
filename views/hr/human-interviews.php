<?php
$pageTitle = 'Human Interviews';
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$interviews = $db->fetchAll(
    "SELECT hi.*, c.full_name as candidate_name, j.title as job_title,
            a.current_stage
     FROM human_interviews hi
     JOIN applications a ON a.id = hi.application_id
     JOIN candidates c ON c.id = a.candidate_id
     JOIN jobs j ON j.id = a.job_id
     WHERE hi.tenant_id = ? ORDER BY hi.scheduled_at DESC LIMIT 50",
    [$tid]
) ?: [];
?>

<?php
/* ── Helpers ────────────────────────────────────────────────── */
function hi_type_badge(string $type): string {
    $map = [
        'technical_screening' => ['Technical Screening', 'bg-indigo-100 text-indigo-700'],
        'cultural_fit'        => ['Cultural Fit',        'bg-pink-100 text-pink-700'],
        'management_round'    => ['Management Round',    'bg-orange-100 text-orange-700'],
        'final_interview'     => ['Final Interview',     'bg-purple-100 text-purple-700'],
    ];
    [$label, $cls] = $map[$type] ?? [ucwords(str_replace('_', ' ', $type)), 'bg-gray-100 text-gray-600'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function hi_status_badge(string $status): string {
    $map = [
        'scheduled' => ['Scheduled', 'bg-blue-100 text-blue-700'],
        'completed' => ['Completed', 'bg-green-100 text-green-700'],
        'cancelled'  => ['Cancelled', 'bg-red-100 text-red-700'],
        'no_show'   => ['No Show',   'bg-gray-100 text-gray-600'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-gray-100 text-gray-600'];
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function hi_format_datetime(string $dt): string {
    $ts = strtotime($dt);
    return $ts ? date('D, M j · g:i A', $ts) : '—';
}

$avatarColors = [
    'bg-violet-100 text-violet-700',
    'bg-blue-100 text-blue-700',
    'bg-emerald-100 text-emerald-700',
    'bg-amber-100 text-amber-700',
    'bg-pink-100 text-pink-700',
    'bg-indigo-100 text-indigo-700',
    'bg-orange-100 text-orange-700',
    'bg-teal-100 text-teal-700',
];
?>

<!-- ── Page header ───────────────────────────────────────────── -->
<div class="max-w-7xl mx-auto">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Human Interviews</h1>
      <p class="text-sm text-gray-500 mt-0.5">Schedule, track, and evaluate interviews with your hiring team</p>
    </div>
    <div class="flex items-center gap-2">
      <!-- View toggle -->
      <div class="flex items-center bg-gray-100 rounded-full p-1" id="view-toggle">
        <button id="btn-list-view" onclick="switchView('list')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all bg-white text-violet-700 shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
          </svg>
          List
        </button>
        <button id="btn-calendar-view" onclick="switchView('calendar')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all text-gray-500 hover:text-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Calendar
        </button>
      </div>
      <!-- Schedule button -->
      <button onclick="openScheduleModal()"
        class="flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors shadow-sm hover:shadow-md">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Schedule Interview
      </button>
    </div>
  </div>

  <!-- ── LIST VIEW ──────────────────────────────────────────── -->
  <div id="list-view">
    <?php if (empty($interviews)): ?>
    <!-- Empty state -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-gray-800 text-lg mb-1">No interviews scheduled</h3>
      <p class="text-sm text-gray-500 mb-5">Schedule your first interview to get started</p>
      <button onclick="openScheduleModal()"
        class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Schedule Interview
      </button>
    </div>

    <?php else: ?>
    <!-- Interviews table card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Candidate</th>
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Position</th>
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Interviewers</th>
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date &amp; Time</th>
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($interviews as $iv):
              $ivId          = (int)$iv['id'];
              $candidateName = htmlspecialchars($iv['candidate_name'] ?? '');
              $jobTitle      = htmlspecialchars($iv['job_title'] ?? '');
              $interviewType = $iv['interview_type'] ?? '';
              $status        = $iv['status'] ?? 'scheduled';
              $scheduledAt   = $iv['scheduled_at'] ?? '';
              $meetingLink   = htmlspecialchars($iv['meeting_link'] ?? '');
              $duration      = (int)($iv['duration_minutes'] ?? 60);
              $notes         = htmlspecialchars($iv['notes'] ?? '');

              // Parse interviewers
              $interviewersRaw = $iv['interviewers'] ?? null;
              $interviewers = [];
              if (is_string($interviewersRaw) && $interviewersRaw !== '') {
                  $decoded = json_decode($interviewersRaw, true);
                  if (is_array($decoded)) $interviewers = $decoded;
              } elseif (is_array($interviewersRaw)) {
                  $interviewers = $interviewersRaw;
              }

              // Data attributes for JS modals
              $dataAttrs = sprintf(
                  'data-id="%d" data-candidate="%s" data-job="%s" data-type="%s" data-datetime="%s" data-duration="%d" data-link="%s" data-notes="%s" data-interviewers="%s"',
                  $ivId,
                  htmlspecialchars($candidateName, ENT_QUOTES),
                  htmlspecialchars($jobTitle, ENT_QUOTES),
                  htmlspecialchars($interviewType, ENT_QUOTES),
                  htmlspecialchars($scheduledAt, ENT_QUOTES),
                  $duration,
                  htmlspecialchars($iv['meeting_link'] ?? '', ENT_QUOTES),
                  htmlspecialchars($notes, ENT_QUOTES),
                  htmlspecialchars(json_encode($interviewers), ENT_QUOTES)
              );
            ?>
            <tr class="hover:bg-gray-50/60 transition-colors group">

              <!-- Candidate -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                    <?= strtoupper(mb_substr($iv['candidate_name'] ?? '?', 0, 1)) ?>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-gray-900"><?= $candidateName ?></p>
                    <?php if (!empty($iv['current_stage'])): ?>
                    <p class="text-xs text-gray-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $iv['current_stage'])) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Position -->
              <td class="px-5 py-4">
                <p class="text-sm text-gray-700 font-medium"><?= $jobTitle ?></p>
              </td>

              <!-- Interviewers -->
              <td class="px-5 py-4">
                <?php if (!empty($interviewers)): ?>
                <div class="flex items-center -space-x-2">
                  <?php foreach (array_slice($interviewers, 0, 4) as $idx => $interviewer):
                    $iName = is_array($interviewer) ? ($interviewer['name'] ?? '') : (string)$interviewer;
                    $iInitial = strtoupper(mb_substr($iName, 0, 1));
                    $colorCls = $avatarColors[$idx % count($avatarColors)];
                  ?>
                  <div class="relative group/avatar">
                    <div class="w-8 h-8 rounded-full border-2 border-white <?= $colorCls ?> flex items-center justify-center text-xs font-bold z-<?= 10 - $idx ?> relative cursor-default"
                         title="<?= htmlspecialchars($iName) ?>">
                      <?= htmlspecialchars($iInitial) ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                  <?php if (count($interviewers) > 4): ?>
                  <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold">
                    +<?= count($interviewers) - 4 ?>
                  </div>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                <span class="text-gray-300 text-sm">—</span>
                <?php endif; ?>
              </td>

              <!-- Date & Time -->
              <td class="px-5 py-4">
                <?php if ($scheduledAt): ?>
                <p class="text-sm text-gray-700 font-medium whitespace-nowrap"><?= hi_format_datetime($scheduledAt) ?></p>
                <?php if ($duration): ?>
                <p class="text-xs text-gray-400 mt-0.5"><?= $duration ?> min</p>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-gray-300 text-sm">—</span>
                <?php endif; ?>
              </td>

              <!-- Type -->
              <td class="px-5 py-4">
                <?= hi_type_badge($interviewType) ?>
              </td>

              <!-- Status -->
              <td class="px-5 py-4">
                <?= hi_status_badge($status) ?>
              </td>

              <!-- Actions -->
              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-1">

                  <!-- Edit -->
                  <button onclick="openEditModal(this)" <?= $dataAttrs ?>
                    class="relative group/tip w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-violet-600 transition-colors"
                    aria-label="Edit interview">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-10">Edit</span>
                  </button>

                  <!-- Send Reminder -->
                  <button onclick="sendReminder(<?= $ivId ?>)"
                    class="relative group/tip w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-amber-600 transition-colors"
                    aria-label="Send reminder">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-10">Send Reminder</span>
                  </button>

                  <?php if ($status === 'scheduled'): ?>
                  <!-- Mark Complete -->
                  <button onclick="openCompleteModal(<?= $ivId ?>)"
                    class="relative group/tip w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-green-600 transition-colors"
                    aria-label="Mark complete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-10">Mark Complete</span>
                  </button>
                  <?php endif; ?>

                  <!-- Cancel -->
                  <button onclick="cancelInterview(<?= $ivId ?>)"
                    class="relative group/tip w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors"
                    aria-label="Cancel interview">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-opacity z-10">Cancel</span>
                  </button>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /list-view -->

  <!-- ── CALENDAR VIEW ──────────────────────────────────────── -->
  <div id="calendar-view" class="hidden">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="w-16 h-16 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <h3 class="font-semibold text-gray-700 text-lg mb-1">Calendar view coming soon</h3>
      <p class="text-sm text-gray-400">We're building a beautiful calendar view for your interviews.</p>
    </div>
  </div>

</div><!-- /max-w-7xl -->


<!-- ══════════════════════════════════════════════════════════
     SCHEDULE INTERVIEW MODAL  #scheduleModal
════════════════════════════════════════════════════════════ -->
<div id="scheduleModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
      <h2 class="text-lg font-semibold text-gray-900">Schedule Interview</h2>
      <button onclick="closeModal('scheduleModal')" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="overflow-y-auto flex-1 px-6 py-5">
      <form id="scheduleForm" class="space-y-5">

        <!-- Candidate search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Candidate <span class="text-red-500">*</span></label>
          <div class="relative">
            <input type="text" id="candidateSearch" autocomplete="off" placeholder="Search by name or email…"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
            <div id="candidateDropdown" class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 hidden overflow-hidden max-h-48 overflow-y-auto"></div>
          </div>
          <input type="hidden" id="schedCandidateId" name="candidate_id">
          <input type="hidden" id="schedApplicationId" name="application_id">
        </div>

        <!-- Position -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Position <span class="text-red-500">*</span></label>
          <select id="schedJobId" name="job_id"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition bg-white">
            <option value="">Select a position…</option>
          </select>
        </div>

        <!-- Interview type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Interview Type</label>
          <select name="interview_type"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition bg-white">
            <option value="technical_screening">Technical Screening</option>
            <option value="cultural_fit">Cultural Fit</option>
            <option value="management_round">Management Round</option>
            <option value="final_interview">Final Interview</option>
          </select>
        </div>

        <!-- Date / Time / Duration row -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
            <input type="date" name="date" required min="<?= date('Y-m-d') ?>"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Time <span class="text-red-500">*</span></label>
            <input type="time" name="time" required
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Duration</label>
            <select name="duration_minutes"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition bg-white">
              <option value="30">30 min</option>
              <option value="45">45 min</option>
              <option value="60" selected>60 min</option>
              <option value="90">90 min</option>
            </select>
          </div>
        </div>

        <!-- Interviewers -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Interviewers</label>
          <div id="interviewersListSched" class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
            <p class="text-sm text-gray-400 italic">Loading team members…</p>
          </div>
        </div>

        <!-- Meeting link -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Meeting Link <span class="text-gray-400 font-normal">(optional)</span></label>
          <input type="url" name="meeting_link" placeholder="https://zoom.us/j/… or Meet/Teams URL"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes for Interviewers</label>
          <textarea name="notes" rows="3"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition resize-none"
            placeholder="Topics to cover, evaluation criteria, context about the candidate…"></textarea>
        </div>

      </form>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
      <button type="button" onclick="closeModal('scheduleModal')"
        class="px-5 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
        Cancel
      </button>
      <button type="button" onclick="submitSchedule()"
        class="px-5 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-full transition-colors shadow-sm" id="scheduleSubmitBtn">
        Schedule Interview
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     EDIT INTERVIEW MODAL  #editModal
════════════════════════════════════════════════════════════ -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
      <h2 class="text-lg font-semibold text-gray-900">Edit Interview</h2>
      <button onclick="closeModal('editModal')" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="overflow-y-auto flex-1 px-6 py-5">
      <form id="editForm" class="space-y-5">
        <input type="hidden" id="editInterviewId" name="id">

        <!-- Candidate (read-only in edit) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Candidate</label>
          <div id="editCandidateName" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-3 py-2.5 text-sm text-gray-700 font-medium"></div>
        </div>

        <!-- Position (read-only in edit) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Position</label>
          <div id="editJobTitle" class="w-full border border-gray-100 bg-gray-50 rounded-xl px-3 py-2.5 text-sm text-gray-700 font-medium"></div>
        </div>

        <!-- Interview type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Interview Type</label>
          <select name="interview_type" id="editInterviewType"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition bg-white">
            <option value="technical_screening">Technical Screening</option>
            <option value="cultural_fit">Cultural Fit</option>
            <option value="management_round">Management Round</option>
            <option value="final_interview">Final Interview</option>
          </select>
        </div>

        <!-- Date / Time / Duration -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
            <input type="date" name="date" id="editDate" min="<?= date('Y-m-d') ?>"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Time</label>
            <input type="time" name="time" id="editTime"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Duration</label>
            <select name="duration_minutes" id="editDuration"
              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition bg-white">
              <option value="30">30 min</option>
              <option value="45">45 min</option>
              <option value="60">60 min</option>
              <option value="90">90 min</option>
            </select>
          </div>
        </div>

        <!-- Interviewers -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Interviewers</label>
          <div id="interviewersListEdit" class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
            <p class="text-sm text-gray-400 italic">Loading team members…</p>
          </div>
        </div>

        <!-- Meeting link -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Meeting Link <span class="text-gray-400 font-normal">(optional)</span></label>
          <input type="url" name="meeting_link" id="editMeetingLink" placeholder="https://zoom.us/j/… or Meet/Teams URL"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition">
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes for Interviewers</label>
          <textarea name="notes" id="editNotes" rows="3"
            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition resize-none"
            placeholder="Topics to cover, evaluation criteria…"></textarea>
        </div>

      </form>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
      <button type="button" onclick="closeModal('editModal')"
        class="px-5 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
        Cancel
      </button>
      <button type="button" onclick="submitEdit()"
        class="px-5 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-full transition-colors shadow-sm" id="editSubmitBtn">
        Save Changes
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MARK COMPLETE / FEEDBACK MODAL  #completeModal
════════════════════════════════════════════════════════════ -->
<div id="completeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
      <h2 class="text-lg font-semibold text-gray-900">Interview Feedback</h2>
      <button onclick="closeModal('completeModal')" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="overflow-y-auto flex-1 px-6 py-5">
      <input type="hidden" id="completeInterviewId">

      <!-- Star rating criteria -->
      <div class="space-y-4 mb-6">
        <p class="text-sm font-semibold text-gray-700">Evaluation Criteria</p>

        <?php
        $criteria = [
            ['communication',   'Communication Skills'],
            ['technical',       'Technical Knowledge'],
            ['cultural_fit',    'Cultural Fit'],
            ['problem_solving', 'Problem Solving'],
            ['overall',         'Overall Impression'],
        ];
        foreach ($criteria as [$key, $label]):
        ?>
        <div class="flex items-center justify-between gap-4">
          <label class="text-sm text-gray-600 w-40 flex-shrink-0"><?= htmlspecialchars($label) ?></label>
          <div class="flex items-center gap-1" data-criterion="<?= $key ?>">
            <?php for ($s = 1; $s <= 5; $s++): ?>
            <button type="button"
              class="star-btn w-7 h-7 transition-transform hover:scale-110 focus:outline-none"
              data-criterion="<?= $key ?>" data-value="<?= $s ?>"
              onclick="setCriterionRating('<?= $key ?>', <?= $s ?>)"
              onmouseenter="hoverStars('<?= $key ?>', <?= $s ?>)"
              onmouseleave="resetStarHover('<?= $key ?>')">
              <svg class="w-6 h-6 star-icon star-<?= $key ?>-<?= $s ?> text-gray-200 transition-colors" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
              </svg>
            </button>
            <?php endfor; ?>
          </div>
          <input type="hidden" id="rating-<?= $key ?>" value="0">
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Feedback textarea -->
      <div class="mb-5">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Overall Feedback &amp; Notes</label>
        <textarea id="completeFeedback" rows="4"
          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none transition resize-none"
          placeholder="Key observations, strengths, areas of concern, follow-up questions…"></textarea>
      </div>

      <!-- Hire recommendation -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">Hire Recommendation</label>
        <div class="flex flex-col gap-2">

          <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-green-50 hover:border-green-300 transition-colors group has-[:checked]:border-green-400 has-[:checked]:bg-green-50">
            <input type="radio" name="hire_recommendation" value="recommend" id="recHire"
              class="w-4 h-4 accent-green-600 flex-shrink-0">
            <div>
              <span class="text-sm font-medium text-gray-800 group-has-[:checked]:text-green-700">Recommend to Hire</span>
              <p class="text-xs text-gray-400">Candidate meets or exceeds requirements</p>
            </div>
          </label>

          <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-red-50 hover:border-red-300 transition-colors group has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
            <input type="radio" name="hire_recommendation" value="do_not_recommend" id="recNoHire"
              class="w-4 h-4 accent-red-600 flex-shrink-0">
            <div>
              <span class="text-sm font-medium text-gray-800 group-has-[:checked]:text-red-700">Do Not Recommend</span>
              <p class="text-xs text-gray-400">Candidate does not meet key requirements</p>
            </div>
          </label>

          <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 hover:border-gray-300 transition-colors group has-[:checked]:border-gray-400 has-[:checked]:bg-gray-50">
            <input type="radio" name="hire_recommendation" value="need_more_info" id="recMore"
              class="w-4 h-4 accent-gray-500 flex-shrink-0">
            <div>
              <span class="text-sm font-medium text-gray-800">Need More Info</span>
              <p class="text-xs text-gray-400">Further interviews or assessment needed</p>
            </div>
          </label>

        </div>
      </div>

    </div><!-- /body -->

    <!-- Footer -->
    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
      <button type="button" onclick="closeModal('completeModal')"
        class="px-5 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
        Cancel
      </button>
      <button type="button" onclick="submitComplete()"
        class="px-5 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-full transition-colors shadow-sm" id="completeSubmitBtn">
        Submit Feedback
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     TOAST CONTAINER
════════════════════════════════════════════════════════════ -->
<div id="toastContainer" class="fixed top-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none"></div>


<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════ -->
<script>
/* ── View toggle ──────────────────────────────────────────── */
function switchView(view) {
  const isCalendar = view === 'calendar';
  document.getElementById('list-view').classList.toggle('hidden', isCalendar);
  document.getElementById('calendar-view').classList.toggle('hidden', !isCalendar);

  const btnList = document.getElementById('btn-list-view');
  const btnCal  = document.getElementById('btn-calendar-view');

  if (isCalendar) {
    btnCal.classList.add('bg-white', 'text-violet-700', 'shadow-sm');
    btnCal.classList.remove('text-gray-500', 'hover:text-gray-700');
    btnList.classList.remove('bg-white', 'text-violet-700', 'shadow-sm');
    btnList.classList.add('text-gray-500', 'hover:text-gray-700');
  } else {
    btnList.classList.add('bg-white', 'text-violet-700', 'shadow-sm');
    btnList.classList.remove('text-gray-500', 'hover:text-gray-700');
    btnCal.classList.remove('bg-white', 'text-violet-700', 'shadow-sm');
    btnCal.classList.add('text-gray-500', 'hover:text-gray-700');
  }
}

/* ── Modal helpers ────────────────────────────────────────── */
function openModal(id) {
  const el = document.getElementById(id);
  el.classList.remove('hidden');
  el.classList.add('flex');
  document.body.classList.add('overflow-hidden');
}
function closeModal(id) {
  const el = document.getElementById(id);
  el.classList.add('hidden');
  el.classList.remove('flex');
  document.body.classList.remove('overflow-hidden');
}

// Close on backdrop click
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

/* ── Toast ────────────────────────────────────────────────── */
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const colors = {
    success: 'bg-green-600',
    error:   'bg-red-600',
    warning: 'bg-amber-500',
    info:    'bg-blue-600',
  };
  const icons = {
    success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
    error:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
    warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
    info:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>',
  };

  const toast = document.createElement('div');
  toast.className = `toast pointer-events-auto flex items-center gap-3 ${colors[type] || colors.info} text-white px-4 py-3 rounded-xl shadow-lg text-sm font-medium max-w-sm opacity-0 translate-y-1 transition-all duration-300`;
  toast.innerHTML = `
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type] || icons.info}</svg>
    <span>${message}</span>
  `;
  container.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.remove('opacity-0', 'translate-y-1');
  });

  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-y-1');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

/* ── Button loading state ─────────────────────────────────── */
function setBtnLoading(btn, loading, label = '') {
  if (loading) {
    btn.dataset.originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = label || 'Saving…';
    btn.classList.add('opacity-70', 'cursor-not-allowed');
  } else {
    btn.disabled = false;
    btn.textContent = btn.dataset.originalText || label;
    btn.classList.remove('opacity-70', 'cursor-not-allowed');
  }
}

/* ── Shared API helper ────────────────────────────────────── */
async function apiCall(url, method = 'GET', body = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(url, opts);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
  return data;
}

/* ── Candidate search (debounced 300ms) ──────────────────── */
let _candidateTimer = null;
const candidateSearchEl  = document.getElementById('candidateSearch');
const candidateDropdown  = document.getElementById('candidateDropdown');
let selectedCandidateId  = null;
let selectedApplicationId = null;

if (candidateSearchEl) {
  candidateSearchEl.addEventListener('input', function () {
    clearTimeout(_candidateTimer);
    const q = this.value.trim();
    if (q.length < 2) { candidateDropdown.classList.add('hidden'); return; }

    _candidateTimer = setTimeout(async () => {
      try {
        const data = await apiCall(`/api/v1/candidates?search=${encodeURIComponent(q)}`);
        const items = Array.isArray(data) ? data : (data.data || data.candidates || []);

        if (!items.length) {
          candidateDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400 italic">No candidates found</div>';
          candidateDropdown.classList.remove('hidden');
          return;
        }

        const avatarColors = ['bg-violet-100 text-violet-700','bg-blue-100 text-blue-700','bg-emerald-100 text-emerald-700','bg-amber-100 text-amber-700','bg-pink-100 text-pink-700'];
        candidateDropdown.innerHTML = items.map((c, i) => {
          const name = c.full_name || c.name || '';
          const email = c.email || '';
          const initial = name ? name[0].toUpperCase() : '?';
          const color = avatarColors[i % avatarColors.length];
          return `
            <button type="button"
              class="w-full text-left px-4 py-3 hover:bg-violet-50 flex items-center gap-3 transition-colors"
              onclick="selectCandidate(${JSON.stringify(c.id)}, ${JSON.stringify(c.application_id || null)}, ${JSON.stringify(name)})">
              <div class="w-9 h-9 rounded-full ${color} flex items-center justify-center text-sm font-bold flex-shrink-0">${initial}</div>
              <div>
                <p class="text-sm font-medium text-gray-900">${name}</p>
                <p class="text-xs text-gray-400">${email}</p>
              </div>
            </button>`;
        }).join('');
        candidateDropdown.classList.remove('hidden');
      } catch (err) {
        candidateDropdown.classList.add('hidden');
      }
    }, 300);
  });

  document.addEventListener('click', function(e) {
    if (!candidateSearchEl.contains(e.target) && !candidateDropdown.contains(e.target)) {
      candidateDropdown.classList.add('hidden');
    }
  });
}

function selectCandidate(candidateId, applicationId, name) {
  selectedCandidateId = candidateId;
  selectedApplicationId = applicationId;
  candidateSearchEl.value = name;
  document.getElementById('schedCandidateId').value = candidateId;
  document.getElementById('schedApplicationId').value = applicationId || '';
  candidateDropdown.classList.add('hidden');
}

/* ── Load jobs for schedule modal ────────────────────────── */
async function loadJobs() {
  const select = document.getElementById('schedJobId');
  try {
    const data = await apiCall('/api/v1/jobs');
    const jobs = Array.isArray(data) ? data : (data.data || data.jobs || []);
    select.innerHTML = '<option value="">Select a position…</option>' +
      jobs.map(j => `<option value="${j.id}">${j.title || j.name || ''}</option>`).join('');
  } catch {
    select.innerHTML = '<option value="">Failed to load positions</option>';
  }
}

/* ── Load team members (checkbox list) ───────────────────── */
async function loadTeamMembers(containerId, selectedIds = []) {
  const container = document.getElementById(containerId);
  try {
    const data = await apiCall('/api/v1/team');
    const members = Array.isArray(data) ? data : (data.data || data.members || data.users || []);
    const colors = ['bg-violet-100 text-violet-700','bg-blue-100 text-blue-700','bg-emerald-100 text-emerald-700','bg-amber-100 text-amber-700','bg-pink-100 text-pink-700','bg-indigo-100 text-indigo-700'];

    if (!members.length) {
      container.innerHTML = '<p class="text-sm text-gray-400 italic">No team members found</p>';
      return;
    }

    container.innerHTML = members.map((m, i) => {
      const name = m.full_name || m.name || '';
      const initial = name ? name[0].toUpperCase() : '?';
      const color = colors[i % colors.length];
      const checked = selectedIds.includes(String(m.id)) ? 'checked' : '';
      return `
        <label class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
          <input type="checkbox" name="interviewer_ids[]" value="${m.id}" ${checked}
            class="w-4 h-4 rounded accent-violet-600 flex-shrink-0">
          <div class="w-8 h-8 rounded-full ${color} flex items-center justify-center text-xs font-bold flex-shrink-0">${initial}</div>
          <span class="text-sm text-gray-700">${name}</span>
        </label>`;
    }).join('');
  } catch {
    container.innerHTML = '<p class="text-sm text-gray-400 italic">Failed to load team members</p>';
  }
}

/* ── Open schedule modal ─────────────────────────────────── */
async function openScheduleModal() {
  document.getElementById('scheduleForm').reset();
  document.getElementById('schedCandidateId').value = '';
  document.getElementById('schedApplicationId').value = '';
  candidateSearchEl.value = '';
  selectedCandidateId = null;
  selectedApplicationId = null;
  openModal('scheduleModal');
  await Promise.all([loadJobs(), loadTeamMembers('interviewersListSched')]);
}

/* ── Submit schedule form ────────────────────────────────── */
async function submitSchedule() {
  const form = document.getElementById('scheduleForm');
  const btn  = document.getElementById('scheduleSubmitBtn');

  const candidateId   = document.getElementById('schedCandidateId').value;
  const applicationId = document.getElementById('schedApplicationId').value;
  if (!candidateId) { showToast('Please select a candidate', 'warning'); return; }

  const fd = new FormData(form);
  const date = fd.get('date');
  const time = fd.get('time');
  if (!date || !time) { showToast('Please set a date and time', 'warning'); return; }

  const interviewerIds = [];
  form.querySelectorAll('input[name="interviewer_ids[]"]:checked').forEach(cb => interviewerIds.push(cb.value));

  const payload = {
    candidate_id:    candidateId,
    application_id:  applicationId || null,
    job_id:          fd.get('job_id'),
    interview_type:  fd.get('interview_type'),
    scheduled_at:    `${date} ${time}:00`,
    duration_minutes: fd.get('duration_minutes'),
    meeting_link:    fd.get('meeting_link') || null,
    notes:           fd.get('notes') || null,
    interviewer_ids: interviewerIds,
  };

  setBtnLoading(btn, true, 'Scheduling…');
  try {
    await apiCall('/api/v1/interviews/schedule', 'POST', payload);
    showToast('Interview scheduled successfully', 'success');
    closeModal('scheduleModal');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showToast(err.message || 'Failed to schedule interview', 'error');
  } finally {
    setBtnLoading(btn, false, 'Schedule Interview');
  }
}

/* ── Open edit modal ─────────────────────────────────────── */
async function openEditModal(btn) {
  const id            = btn.dataset.id;
  const candidate     = btn.dataset.candidate;
  const job           = btn.dataset.job;
  const type          = btn.dataset.type;
  const datetime      = btn.dataset.datetime;
  const duration      = btn.dataset.duration;
  const link          = btn.dataset.link;
  const notes         = btn.dataset.notes;
  const interviewers  = JSON.parse(btn.dataset.interviewers || '[]');

  document.getElementById('editInterviewId').value  = id;
  document.getElementById('editCandidateName').textContent = candidate;
  document.getElementById('editJobTitle').textContent      = job;
  document.getElementById('editInterviewType').value       = type;
  document.getElementById('editMeetingLink').value         = link;
  document.getElementById('editNotes').value               = notes;

  if (datetime) {
    const d = new Date(datetime.replace(' ', 'T'));
    if (!isNaN(d)) {
      document.getElementById('editDate').value = d.toISOString().slice(0, 10);
      document.getElementById('editTime').value = d.toTimeString().slice(0, 5);
    }
  }

  const durSel = document.getElementById('editDuration');
  for (let opt of durSel.options) opt.selected = (opt.value == duration);

  openModal('editModal');

  const selectedIds = interviewers.map(iv => String(typeof iv === 'object' ? (iv.id || iv) : iv));
  await loadTeamMembers('interviewersListEdit', selectedIds);
}

/* ── Submit edit form ────────────────────────────────────── */
async function submitEdit() {
  const btn  = document.getElementById('editSubmitBtn');
  const form = document.getElementById('editForm');
  const id   = document.getElementById('editInterviewId').value;

  const fd = new FormData(form);
  const date = fd.get('date');
  const time = fd.get('time');
  if (!date || !time) { showToast('Please set a date and time', 'warning'); return; }

  const interviewerIds = [];
  form.querySelectorAll('input[name="interviewer_ids[]"]:checked').forEach(cb => interviewerIds.push(cb.value));

  const payload = {
    interview_type:   fd.get('interview_type'),
    scheduled_at:     `${date} ${time}:00`,
    duration_minutes: fd.get('duration_minutes'),
    meeting_link:     fd.get('meeting_link') || null,
    notes:            fd.get('notes') || null,
    interviewer_ids:  interviewerIds,
  };

  setBtnLoading(btn, true, 'Saving…');
  try {
    await apiCall(`/api/v1/interviews/${id}`, 'PUT', payload);
    showToast('Interview updated successfully', 'success');
    closeModal('editModal');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showToast(err.message || 'Failed to update interview', 'error');
  } finally {
    setBtnLoading(btn, false, 'Save Changes');
  }
}

/* ── Star rating ─────────────────────────────────────────── */
const criteriaRatings = {
  communication: 0, technical: 0, cultural_fit: 0, problem_solving: 0, overall: 0
};

function renderStars(criterion, value) {
  for (let s = 1; s <= 5; s++) {
    const icon = document.querySelector(`.star-${criterion}-${s}`);
    if (icon) {
      icon.classList.toggle('text-amber-400', s <= value);
      icon.classList.toggle('text-gray-200', s > value);
    }
  }
}

function setCriterionRating(criterion, value) {
  criteriaRatings[criterion] = value;
  document.getElementById(`rating-${criterion}`).value = value;
  renderStars(criterion, value);
}

function hoverStars(criterion, value) {
  for (let s = 1; s <= 5; s++) {
    const icon = document.querySelector(`.star-${criterion}-${s}`);
    if (icon) {
      icon.classList.toggle('text-amber-300', s <= value);
      icon.classList.toggle('text-amber-400', false);
      icon.classList.toggle('text-gray-200', s > value);
    }
  }
}

function resetStarHover(criterion) {
  renderStars(criterion, criteriaRatings[criterion]);
}

/* ── Open complete modal ─────────────────────────────────── */
function openCompleteModal(id) {
  document.getElementById('completeInterviewId').value = id;
  document.getElementById('completeFeedback').value = '';
  document.querySelectorAll('input[name="hire_recommendation"]').forEach(r => r.checked = false);

  // Reset ratings
  Object.keys(criteriaRatings).forEach(k => {
    criteriaRatings[k] = 0;
    document.getElementById(`rating-${k}`).value = 0;
    renderStars(k, 0);
  });

  openModal('completeModal');
}

/* ── Submit complete form ────────────────────────────────── */
async function submitComplete() {
  const btn = document.getElementById('completeSubmitBtn');
  const id  = document.getElementById('completeInterviewId').value;

  const recEl = document.querySelector('input[name="hire_recommendation"]:checked');
  if (!recEl) { showToast('Please select a hire recommendation', 'warning'); return; }

  const ratings = {};
  Object.keys(criteriaRatings).forEach(k => { ratings[k] = criteriaRatings[k]; });

  const payload = {
    feedback:           document.getElementById('completeFeedback').value,
    hire_recommendation: recEl.value,
    ratings,
  };

  setBtnLoading(btn, true, 'Submitting…');
  try {
    await apiCall(`/api/v1/interviews/${id}/complete`, 'POST', payload);
    showToast('Feedback submitted successfully', 'success');
    closeModal('completeModal');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showToast(err.message || 'Failed to submit feedback', 'error');
  } finally {
    setBtnLoading(btn, false, 'Submit Feedback');
  }
}

/* ── Cancel interview ────────────────────────────────────── */
async function cancelInterview(id) {
  const confirmed = confirm('Are you sure you want to cancel this interview? This will notify the candidate and interviewers.');
  if (!confirmed) return;

  try {
    await apiCall(`/api/v1/interviews/${id}/cancel`, 'POST');
    showToast('Interview cancelled', 'success');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    showToast(err.message || 'Failed to cancel interview', 'error');
  }
}

/* ── Send reminder ───────────────────────────────────────── */
async function sendReminder(id) {
  try {
    await apiCall(`/api/v1/interviews/${id}/remind`, 'POST');
    showToast('Reminder sent successfully', 'success');
  } catch (err) {
    showToast(err.message || 'Failed to send reminder', 'error');
  }
}
</script>
