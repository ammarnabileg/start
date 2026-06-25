<?php
ob_start();
$pageTitle = 'Human Interviews';
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
try {
    $interviews = $db->fetchAll(
        "SELECT hi.*, CONCAT(c.first_name,' ',c.last_name) as candidate_name, j.title as job_title,
                a.current_stage
         FROM human_interviews hi
         JOIN applications a ON a.id = hi.application_id
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         WHERE a.tenant_id = ? ORDER BY hi.scheduled_at DESC LIMIT 50",
        [$tid]
    ) ?: [];
} catch (\Exception $e) { $interviews = []; }
?>

<div class="p-6 max-w-7xl mx-auto">

  <!-- Page Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Human Interviews</h1>
      <p class="text-sm text-gray-500 mt-1">Manage and track all scheduled interviews</p>
    </div>
    <div class="flex items-center gap-3">
      <!-- Export Button -->
      <button onclick="exportInterviews()"
        class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Export
      </button>
      <!-- View Toggle -->
      <div class="flex items-center bg-gray-100 rounded-full p-1">
        <button id="btnListView" onclick="setView('list')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all bg-white text-gray-900 shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
          </svg>
          List
        </button>
        <button id="btnCalView" onclick="setView('calendar')"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-all text-gray-500">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Calendar
        </button>
      </div>
      <button onclick="openScheduleModal()"
        class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Schedule Interview
      </button>
    </div>
  </div>

  <!-- List View -->
  <div id="listView">
    <!-- Status Filter Bar -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button onclick="filterByStatus('all')" id="filter-all"
        class="status-filter-btn px-4 py-1.5 rounded-full text-sm font-medium bg-violet-600 text-white transition-colors">
        All
      </button>
      <button onclick="filterByStatus('scheduled')" id="filter-scheduled"
        class="status-filter-btn px-4 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
        Scheduled
      </button>
      <button onclick="filterByStatus('completed')" id="filter-completed"
        class="status-filter-btn px-4 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
        Completed
      </button>
      <button onclick="filterByStatus('cancelled')" id="filter-cancelled"
        class="status-filter-btn px-4 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
        Cancelled
      </button>
      <button onclick="filterByStatus('no_show')" id="filter-no_show"
        class="status-filter-btn px-4 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
        No Show
      </button>
    </div>
    <?php if (empty($interviews)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="flex justify-center mb-4">
        <div class="w-16 h-16 bg-violet-50 rounded-full flex items-center justify-center">
          <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-1">No interviews scheduled</h3>
      <p class="text-gray-500 text-sm mb-6">Schedule your first interview to get started</p>
      <button onclick="openScheduleModal()"
        class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
        Schedule Interview
      </button>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Candidate</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Position</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Interviewers</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date &amp; Time</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php
            $statusClasses = [
              'scheduled' => 'bg-blue-100 text-blue-700',
              'completed' => 'bg-green-100 text-green-700',
              'cancelled' => 'bg-red-100 text-red-700',
              'no_show'   => 'bg-gray-100 text-gray-600',
              'no show'   => 'bg-gray-100 text-gray-600',
            ];
            $statusLabels = [
              'scheduled' => 'Scheduled',
              'completed' => 'Completed',
              'cancelled' => 'Cancelled',
              'no_show'   => 'No Show',
              'no show'   => 'No Show',
            ];
            $typeBadges = [
              'Technical Screening' => 'bg-indigo-100 text-indigo-700',
              'Cultural Fit'        => 'bg-pink-100 text-pink-700',
              'Management Round'    => 'bg-orange-100 text-orange-700',
              'Final Interview'     => 'bg-purple-100 text-purple-700',
            ];
            $avatarPalette = ['bg-violet-500','bg-pink-500','bg-amber-500','bg-teal-500','bg-blue-500','bg-rose-500'];

            foreach ($interviews as $iv):
              $scheduledTs  = strtotime($iv['scheduled_at'] ?? 'now');
              $formattedDt  = date('D, M j', $scheduledTs) . ' · ' . date('g:i A', $scheduledTs);
              $rawStatus    = strtolower(trim($iv['status'] ?? 'scheduled'));
              $type         = $iv['interview_type'] ?? $iv['type'] ?? 'Technical Screening';

              $interviewers = [];
              if (!empty($iv['interviewers'])) {
                $decoded = is_string($iv['interviewers']) ? json_decode($iv['interviewers'], true) : $iv['interviewers'];
                if (is_array($decoded)) $interviewers = $decoded;
              }

              $statusCls = $statusClasses[$rawStatus] ?? 'bg-gray-100 text-gray-600';
              $statusLbl = $statusLabels[$rawStatus] ?? ucwords(str_replace('_', ' ', $rawStatus));
              $typeCls   = $typeBadges[$type]   ?? 'bg-indigo-100 text-indigo-700';

              // Build data for JS modals
              $jsData = htmlspecialchars(json_encode([
                'id'           => $iv['id'],
                'candidate'    => $iv['candidate_name'] ?? '',
                'candidate_id' => $iv['candidate_id'] ?? '',
                'job_title'    => $iv['job_title'] ?? '',
                'job_id'       => $iv['job_id'] ?? '',
                'type'         => $type,
                'datetime'     => $iv['scheduled_at'] ?? '',
                'duration'     => $iv['duration_minutes'] ?? 60,
                'link'         => $iv['meeting_link'] ?? '',
                'notes'        => $iv['notes'] ?? '',
                'interviewers' => $interviewers,
              ]), ENT_QUOTES, 'UTF-8');
            ?>
            <tr class="hover:bg-gray-50 transition-colors" data-status="<?= htmlspecialchars($rawStatus) ?>">
              <!-- Candidate -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                    <?= strtoupper(mb_substr($iv['candidate_name'] ?? 'U', 0, 1)) ?>
                  </div>
                  <div>
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($iv['candidate_name'] ?? '') ?></div>
                    <?php if (!empty($iv['current_stage'])): ?>
                    <div class="text-xs text-gray-400"><?= htmlspecialchars($iv['current_stage']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <!-- Position -->
              <td class="px-6 py-4">
                <span class="font-medium text-gray-700"><?= htmlspecialchars($iv['job_title'] ?? '') ?></span>
              </td>
              <!-- Interviewers -->
              <td class="px-6 py-4">
                <?php if (!empty($interviewers)): ?>
                <div class="flex items-center -space-x-2">
                  <?php foreach (array_slice($interviewers, 0, 4) as $idx => $interviewer):
                    $ivName = is_array($interviewer) ? ($interviewer['name'] ?? $interviewer['full_name'] ?? '') : (string)$interviewer;
                    $colorCls = $avatarPalette[$idx % count($avatarPalette)];
                  ?>
                  <div title="<?= htmlspecialchars($ivName) ?>"
                    class="w-7 h-7 rounded-full <?= $colorCls ?> text-white flex items-center justify-center text-xs font-bold ring-2 ring-white">
                    <?= strtoupper(mb_substr($ivName, 0, 1)) ?>
                  </div>
                  <?php endforeach; ?>
                  <?php if (count($interviewers) > 4): ?>
                  <div class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold ring-2 ring-white">
                    +<?= count($interviewers) - 4 ?>
                  </div>
                  <?php endif; ?>
                </div>
                <?php else: ?>
                <span class="text-gray-400 text-xs">—</span>
                <?php endif; ?>
              </td>
              <!-- Date & Time -->
              <td class="px-6 py-4">
                <div class="text-gray-700"><?= htmlspecialchars($formattedDt) ?></div>
                <?php if (!empty($iv['duration_minutes'])): ?>
                <div class="text-xs text-gray-400"><?= (int)$iv['duration_minutes'] ?> min</div>
                <?php endif; ?>
              </td>
              <!-- Type -->
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $typeCls ?>">
                  <?= htmlspecialchars($type) ?>
                </span>
              </td>
              <!-- Status -->
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>">
                  <?= htmlspecialchars($statusLbl) ?>
                </span>
              </td>
              <!-- Actions -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-1">
                  <!-- Edit -->
                  <button onclick='openEditModal(<?= $jsData ?>)'
                    title="Edit Interview"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-violet-600 hover:bg-violet-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <!-- Cancel (not shown for already cancelled/completed) -->
                  <?php if (!in_array($rawStatus, ['cancelled', 'completed'])): ?>
                  <button onclick="confirmCancel(<?= (int)$iv['id'] ?>)"
                    title="Cancel Interview"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                  <?php endif; ?>
                  <!-- Send Reminder (scheduled only) -->
                  <?php if ($rawStatus === 'scheduled'): ?>
                  <button onclick="sendReminder(<?= (int)$iv['id'] ?>)"
                    title="Send Reminder"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                  </button>
                  <!-- Mark Complete (scheduled only) -->
                  <button onclick="openCompleteModal(<?= (int)$iv['id'] ?>)"
                    title="Mark Complete"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Calendar View (placeholder) -->
  <div id="calendarView" class="hidden">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="flex justify-center mb-4">
        <div class="w-16 h-16 bg-violet-50 rounded-full flex items-center justify-center">
          <svg class="w-8 h-8 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-1">Calendar view coming soon</h3>
      <p class="text-gray-500 text-sm">We're building a beautiful calendar experience for you.</p>
    </div>
  </div>

</div>

<!-- ============================================================
     SCHEDULE INTERVIEW MODAL
     ============================================================ -->
<div id="scheduleModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="display:none">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('scheduleModal')"></div>
  <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
      <h2 class="text-lg font-semibold text-gray-900">Schedule Interview</h2>
      <button onclick="closeModal('scheduleModal')" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <form id="scheduleForm" onsubmit="submitSchedule(event)" class="p-6 space-y-5">
      <input type="hidden" id="schedCandidateId" name="candidate_id">

      <!-- Candidate Search -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Candidate <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <input type="text" id="candidateSearch"
            placeholder="Search by name or email…"
            autocomplete="off"
            oninput="debouncedCandidateSearch(this.value)"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          <div id="candidateDropdown"
            class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-50 max-h-48 overflow-y-auto hidden">
          </div>
        </div>
      </div>

      <!-- Position -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Position <span class="text-red-500">*</span>
        </label>
        <select id="schedJobId" name="job_id" required
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          <option value="">Loading positions…</option>
        </select>
      </div>

      <!-- Interview Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Interview Type <span class="text-red-500">*</span>
        </label>
        <select name="interview_type" required
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          <option value="Technical Screening">Technical Screening</option>
          <option value="Cultural Fit">Cultural Fit</option>
          <option value="Management Round">Management Round</option>
          <option value="Final Interview">Final Interview</option>
        </select>
      </div>

      <!-- Date / Time / Duration -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
          <input type="date" name="date" required min="<?= date('Y-m-d') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Time <span class="text-red-500">*</span></label>
          <input type="time" name="time" required
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Duration</label>
          <select name="duration_minutes"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
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
        <div id="schedInterviewers"
          class="border border-gray-200 rounded-xl p-3 max-h-44 overflow-y-auto space-y-1">
          <p class="text-sm text-gray-400 px-1">Loading team members…</p>
        </div>
      </div>

      <!-- Meeting Link -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Meeting Link
          <span class="text-gray-400 text-xs font-normal ml-1">(optional)</span>
        </label>
        <input type="url" name="meeting_link"
          placeholder="https://zoom.us/j/… or Meet/Teams URL"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes for Interviewers</label>
        <textarea name="notes" rows="3"
          placeholder="Areas to focus on, background context, preparation notes…"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"></textarea>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onclick="closeModal('scheduleModal')"
          class="px-4 py-2 rounded-full text-sm font-medium text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit"
          class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Schedule Interview
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     EDIT INTERVIEW MODAL
     ============================================================ -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="display:none">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('editModal')"></div>
  <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
      <h2 class="text-lg font-semibold text-gray-900">Edit Interview</h2>
      <button onclick="closeModal('editModal')" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <form id="editForm" onsubmit="submitEdit(event)" class="p-6 space-y-5">
      <input type="hidden" id="editInterviewId" name="id">
      <input type="hidden" id="editCandidateId" name="candidate_id">

      <!-- Candidate (read-only) -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Candidate</label>
        <input type="text" id="editCandidateName" readonly
          class="w-full border border-gray-100 bg-gray-50 rounded-xl px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
      </div>

      <!-- Position -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Position <span class="text-red-500">*</span>
        </label>
        <select id="editJobId" name="job_id" required
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          <option value="">Loading positions…</option>
        </select>
      </div>

      <!-- Interview Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Interview Type <span class="text-red-500">*</span>
        </label>
        <select id="editType" name="interview_type" required
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
          <option value="Technical Screening">Technical Screening</option>
          <option value="Cultural Fit">Cultural Fit</option>
          <option value="Management Round">Management Round</option>
          <option value="Final Interview">Final Interview</option>
        </select>
      </div>

      <!-- Date / Time / Duration -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
          <input type="date" id="editDate" name="date" required min="<?= date('Y-m-d') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Time <span class="text-red-500">*</span></label>
          <input type="time" id="editTime" name="time" required
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Duration</label>
          <select id="editDuration" name="duration_minutes"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
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
        <div id="editInterviewers"
          class="border border-gray-200 rounded-xl p-3 max-h-44 overflow-y-auto space-y-1">
          <p class="text-sm text-gray-400 px-1">Loading team members…</p>
        </div>
      </div>

      <!-- Meeting Link -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
          Meeting Link <span class="text-gray-400 text-xs font-normal ml-1">(optional)</span>
        </label>
        <input type="url" id="editMeetingLink" name="meeting_link"
          placeholder="https://zoom.us/j/… or Meet/Teams URL"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes for Interviewers</label>
        <textarea id="editNotes" name="notes" rows="3"
          placeholder="Areas to focus on, background context…"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"></textarea>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onclick="closeModal('editModal')"
          class="px-4 py-2 rounded-full text-sm font-medium text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit"
          class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-full text-sm font-medium transition-colors">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     INTERVIEW FEEDBACK / MARK COMPLETE MODAL
     ============================================================ -->
<div id="completeModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="display:none">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('completeModal')"></div>
  <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-6 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
      <h2 class="text-lg font-semibold text-gray-900">Interview Feedback</h2>
      <button onclick="closeModal('completeModal')" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <form id="completeForm" onsubmit="submitComplete(event)" class="p-6 space-y-6">
      <input type="hidden" id="completeInterviewId" name="id">

      <!-- Star Ratings -->
      <div>
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Rate Performance</h3>
        <div class="space-y-4">
          <?php
          $ratingCriteria = [
            ['key' => 'communication',   'label' => 'Communication Skills'],
            ['key' => 'technical',        'label' => 'Technical Knowledge'],
            ['key' => 'cultural_fit',     'label' => 'Cultural Fit'],
            ['key' => 'problem_solving',  'label' => 'Problem Solving'],
            ['key' => 'overall',          'label' => 'Overall Impression'],
            ['key' => 'experience',       'label' => 'Relevant Experience'],
          ];
          foreach ($ratingCriteria as $criterion): ?>
          <div class="flex items-center justify-between gap-4">
            <label class="text-sm font-medium text-gray-700 w-44 flex-shrink-0">
              <?= htmlspecialchars($criterion['label']) ?>
            </label>
            <div class="flex items-center gap-1 star-group" data-key="<?= $criterion['key'] ?>">
              <input type="hidden" name="rating_<?= $criterion['key'] ?>" id="rating_<?= $criterion['key'] ?>" value="0">
              <?php for ($s = 1; $s <= 5; $s++): ?>
              <button type="button"
                class="star-btn text-gray-300 hover:text-amber-400 transition-colors focus:outline-none"
                data-value="<?= $s ?>"
                onclick="setRating('<?= $criterion['key'] ?>', <?= $s ?>)"
                onmouseover="hoverStars('<?= $criterion['key'] ?>', <?= $s ?>)"
                onmouseleave="unhoverStars('<?= $criterion['key'] ?>')">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              </button>
              <?php endfor; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Overall Feedback -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Overall Feedback &amp; Notes</label>
        <textarea name="feedback" rows="4"
          placeholder="Candidate's strengths, areas for improvement, specific observations…"
          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none"></textarea>
      </div>

      <!-- Hire Recommendation -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">Hire Recommendation</label>
        <div class="space-y-3">
          <label class="flex items-start gap-3 cursor-pointer group p-3 rounded-xl hover:bg-green-50 transition-colors border border-transparent hover:border-green-100">
            <input type="radio" name="recommendation" value="recommend" required
              class="mt-0.5 w-4 h-4 text-green-600 border-gray-300 focus:ring-green-500">
            <div>
              <div class="text-sm font-semibold text-green-700">Recommend to Hire</div>
              <div class="text-xs text-gray-400 mt-0.5">Strong candidate — proceed to offer stage</div>
            </div>
          </label>
          <label class="flex items-start gap-3 cursor-pointer group p-3 rounded-xl hover:bg-red-50 transition-colors border border-transparent hover:border-red-100">
            <input type="radio" name="recommendation" value="do_not_recommend"
              class="mt-0.5 w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500">
            <div>
              <div class="text-sm font-semibold text-red-700">Do Not Recommend</div>
              <div class="text-xs text-gray-400 mt-0.5">Not the right fit at this time</div>
            </div>
          </label>
          <label class="flex items-start gap-3 cursor-pointer group p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
            <input type="radio" name="recommendation" value="need_more_info"
              class="mt-0.5 w-4 h-4 text-gray-500 border-gray-300 focus:ring-gray-500">
            <div>
              <div class="text-sm font-semibold text-gray-700">Need More Info</div>
              <div class="text-xs text-gray-400 mt-0.5">Additional interview or assessment needed</div>
            </div>
          </label>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onclick="closeModal('completeModal')"
          class="px-4 py-2 rounded-full text-sm font-medium text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit"
          class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Submit Feedback
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-5 right-5 z-[100] space-y-2 pointer-events-none"></div>

<script>
(function () {
  'use strict';

  /* ──────────────────────────── State ──────────────────────────── */
  let _searchTimer = null;
  let _teamMembers = [];
  let _jobsList    = [];

  const AVATAR_COLORS = [
    'bg-violet-500','bg-pink-500','bg-amber-500',
    'bg-teal-500','bg-blue-500','bg-rose-500'
  ];

  /* ──────────────────────────── Toast ──────────────────────────── */
  window.showToast = function (message, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    var colorMap = {
      success : 'bg-green-600',
      error   : 'bg-red-600',
      info    : 'bg-violet-600',
      warning : 'bg-amber-500'
    };
    var iconPath = type === 'error'
      ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
      : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
    toast.className = 'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg transition-all duration-300 ' + (colorMap[type] || colorMap.info);
    toast.innerHTML = '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + iconPath + '</svg><span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      setTimeout(function () { toast.remove(); }, 350);
    }, 3000);
  };

  /* ──────────────────────────── Modals ─────────────────────────── */
  window.closeModal = function (id) {
    var el = document.getElementById(id);
    el.style.display = 'none';
    el.classList.add('hidden');
  };

  function openModal(id) {
    var el = document.getElementById(id);
    el.classList.remove('hidden');
    el.style.display = 'flex';
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      ['scheduleModal','editModal','completeModal'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el && el.style.display !== 'none') closeModal(id);
      });
    }
  });

  /* ──────────────────────── View Toggle ────────────────────────── */
  window.setView = function (view) {
    var listView = document.getElementById('listView');
    var calView  = document.getElementById('calendarView');
    var btnList  = document.getElementById('btnListView');
    var btnCal   = document.getElementById('btnCalView');

    if (view === 'list') {
      listView.classList.remove('hidden');
      calView.classList.add('hidden');
      btnList.classList.add('bg-white','text-gray-900','shadow-sm');
      btnList.classList.remove('text-gray-500');
      btnCal.classList.remove('bg-white','text-gray-900','shadow-sm');
      btnCal.classList.add('text-gray-500');
    } else {
      listView.classList.add('hidden');
      calView.classList.remove('hidden');
      btnCal.classList.add('bg-white','text-gray-900','shadow-sm');
      btnCal.classList.remove('text-gray-500');
      btnList.classList.remove('bg-white','text-gray-900','shadow-sm');
      btnList.classList.add('text-gray-500');
    }
  };

  /* ────────────────────── Candidate Search ─────────────────────── */
  window.debouncedCandidateSearch = function (query) {
    clearTimeout(_searchTimer);
    var dd = document.getElementById('candidateDropdown');
    if (!query || query.length < 2) { dd.classList.add('hidden'); return; }
    _searchTimer = setTimeout(function () { _searchCandidates(query); }, 300);
  };

  function _searchCandidates(query) {
    fetch('/api/v1/candidates?search=' + encodeURIComponent(query))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var candidates = data.data || data.candidates || (Array.isArray(data) ? data : []);
        var dd = document.getElementById('candidateDropdown');
        if (!candidates.length) {
          dd.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">No candidates found</div>';
        } else {
          dd.innerHTML = candidates.map(function (c) {
            var name = (c.full_name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.name || '').replace(/'/g, "\\'");
            var initial = (c.full_name || c.first_name || c.name || 'U')[0].toUpperCase();
            var email = c.email ? '<div class="text-xs text-gray-400">' + c.email + '</div>' : '';
            return '<div class="px-4 py-2.5 hover:bg-violet-50 cursor-pointer flex items-center gap-3 transition-colors" onclick="selectCandidate(' + c.id + ', \'' + name + '\')">' +
              '<div class="w-7 h-7 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-bold flex-shrink-0">' + initial + '</div>' +
              '<div><div class="text-sm font-medium text-gray-900">' + (c.full_name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.name || '') + '</div>' + email + '</div></div>';
          }).join('');
        }
        dd.classList.remove('hidden');
      })
      .catch(function () {});
  }

  window.selectCandidate = function (id, name) {
    document.getElementById('schedCandidateId').value = id;
    document.getElementById('candidateSearch').value = name;
    document.getElementById('candidateDropdown').classList.add('hidden');
  };

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#candidateSearch') && !e.target.closest('#candidateDropdown')) {
      var dd = document.getElementById('candidateDropdown');
      if (dd) dd.classList.add('hidden');
    }
  });

  /* ───────────────────────── Load Jobs ─────────────────────────── */
  function _loadJobs(selectId, selectedValue) {
    if (_jobsList.length) { _populateJobSelect(selectId, selectedValue); return; }
    fetch('/api/v1/jobs')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        _jobsList = data.data || data.jobs || (Array.isArray(data) ? data : []);
        _populateJobSelect(selectId, selectedValue);
      })
      .catch(function () {
        document.getElementById(selectId).innerHTML = '<option value="">Failed to load positions</option>';
      });
  }

  function _populateJobSelect(selectId, selectedValue) {
    var sel = document.getElementById(selectId);
    sel.innerHTML = '<option value="">Select position…</option>' +
      _jobsList.map(function (j) {
        var sel2 = String(j.id) === String(selectedValue) ? ' selected' : '';
        return '<option value="' + j.id + '"' + sel2 + '>' + (j.title || j.name || '') + '</option>';
      }).join('');
  }

  /* ─────────────────────── Load Team Members ───────────────────── */
  function _loadTeam(containerId, selectedIds) {
    selectedIds = selectedIds || [];
    if (_teamMembers.length) { _renderTeamCheckboxes(containerId, selectedIds); return; }
    fetch('/api/v1/team')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        _teamMembers = data.data || data.members || (Array.isArray(data) ? data : []);
        _renderTeamCheckboxes(containerId, selectedIds);
      })
      .catch(function () {
        document.getElementById(containerId).innerHTML = '<p class="text-sm text-red-400 px-1">Failed to load team members</p>';
      });
  }

  function _renderTeamCheckboxes(containerId, selectedIds) {
    var container = document.getElementById(containerId);
    var selectedSet = {};
    (selectedIds || []).forEach(function (id) { selectedSet[String(id)] = true; });

    if (!_teamMembers.length) {
      container.innerHTML = '<p class="text-sm text-gray-400 px-1">No team members found</p>';
      return;
    }
    container.innerHTML = _teamMembers.map(function (m, i) {
      var name = m.name || m.full_name || '';
      var colorCls = AVATAR_COLORS[i % AVATAR_COLORS.length];
      var checked = selectedSet[String(m.id)] ? ' checked' : '';
      var role = (m.role || m.job_title) ? '<div class="text-xs text-gray-400">' + (m.role || m.job_title) + '</div>' : '';
      return '<label class="flex items-center gap-3 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1.5 transition-colors">' +
        '<input type="checkbox" name="interviewer_ids[]" value="' + m.id + '"' + checked + ' class="w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">' +
        '<div class="w-7 h-7 rounded-full ' + colorCls + ' text-white flex items-center justify-center text-xs font-bold flex-shrink-0">' + (name[0] || '?').toUpperCase() + '</div>' +
        '<div><div class="text-sm font-medium text-gray-800">' + name + '</div>' + role + '</div></label>';
    }).join('');
  }

  /* ─────────────────────── Schedule Modal ─────────────────────── */
  window.openScheduleModal = function () {
    document.getElementById('scheduleForm').reset();
    document.getElementById('schedCandidateId').value = '';
    document.getElementById('candidateSearch').value = '';
    openModal('scheduleModal');
    _loadJobs('schedJobId', '');
    _loadTeam('schedInterviewers', []);
  };

  window.submitSchedule = function (e) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    var body = { interviewer_ids: [] };
    fd.forEach(function (v, k) {
      if (k === 'interviewer_ids[]') { body.interviewer_ids.push(v); }
      else { body[k] = v; }
    });
    body.candidate_id = document.getElementById('schedCandidateId').value;
    if (!body.candidate_id) { showToast('Please select a candidate', 'error'); return; }
    if (!body.job_id)       { showToast('Please select a position', 'error'); return; }

    fetch('/api/v1/hr-interviews?action=schedule', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
      if (res.ok && res.data.ok) {
        showToast('Interview scheduled successfully', 'success');
        closeModal('scheduleModal');
        setTimeout(function () { location.reload(); }, 900);
      } else {
        showToast(res.data.message || 'Failed to schedule interview', 'error');
      }
    })
    .catch(function () { showToast('Network error. Please try again.', 'error'); });
  };

  /* ──────────────────────── Edit Modal ────────────────────────── */
  window.openEditModal = function (data) {
    document.getElementById('editInterviewId').value  = data.id || '';
    document.getElementById('editCandidateId').value  = data.candidate_id || '';
    document.getElementById('editCandidateName').value = data.candidate || '';
    document.getElementById('editMeetingLink').value  = data.link || '';
    document.getElementById('editNotes').value        = data.notes || '';

    // Type
    var typeEl = document.getElementById('editType');
    for (var i = 0; i < typeEl.options.length; i++) {
      if (typeEl.options[i].value === data.type) { typeEl.selectedIndex = i; break; }
    }

    // Date/time
    if (data.datetime) {
      var dt = new Date(data.datetime);
      if (!isNaN(dt)) {
        document.getElementById('editDate').value = dt.toISOString().slice(0, 10);
        document.getElementById('editTime').value = ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
      }
    }

    // Duration
    var durEl = document.getElementById('editDuration');
    for (var j = 0; j < durEl.options.length; j++) {
      if (parseInt(durEl.options[j].value) === parseInt(data.duration)) { durEl.selectedIndex = j; break; }
    }

    openModal('editModal');
    var existingIds = (data.interviewers || []).map(function (iv) {
      return typeof iv === 'object' ? (iv.id || '') : iv;
    });
    _loadJobs('editJobId', data.job_id || '');
    _loadTeam('editInterviewers', existingIds);
  };

  window.submitEdit = function (e) {
    e.preventDefault();
    var id = document.getElementById('editInterviewId').value;
    var fd = new FormData(e.target);
    var body = { interviewer_ids: [] };
    fd.forEach(function (v, k) {
      if (k === 'interviewer_ids[]') { body.interviewer_ids.push(v); }
      else { body[k] = v; }
    });

    body.id = id;
    fetch('/api/v1/hr-interviews?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
      if (res.ok && res.data.ok) {
        showToast('Interview updated successfully', 'success');
        closeModal('editModal');
        setTimeout(function () { location.reload(); }, 900);
      } else {
        showToast(res.data.message || 'Failed to update interview', 'error');
      }
    })
    .catch(function () { showToast('Network error. Please try again.', 'error'); });
  };

  /* ──────────────────────── Cancel ───────────────────────────── */
  window.confirmCancel = function (id) {
    if (!confirm('Are you sure you want to cancel this interview? This will notify the candidate and interviewers.')) return;
    fetch('/api/v1/hr-interviews?action=cancel', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id: id })
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
      if (res.ok && res.data.ok) {
        showToast('Interview cancelled', 'warning');
        setTimeout(function () { location.reload(); }, 900);
      } else {
        showToast(res.data.message || 'Failed to cancel interview', 'error');
      }
    })
    .catch(function () { showToast('Network error. Please try again.', 'error'); });
  };

  /* ─────────────────────── Send Reminder ────────────────────── */
  window.sendReminder = function (id) {
    fetch('/api/v1/hr-interviews?action=remind', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id: id })
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
      if (res.ok && res.data.ok) {
        showToast('Reminder sent successfully', 'success');
      } else {
        showToast(res.data.message || 'Failed to send reminder', 'error');
      }
    })
    .catch(function () { showToast('Network error. Please try again.', 'error'); });
  };

  /* ──────────────────── Mark Complete Modal ────────────────── */
  window.openCompleteModal = function (id) {
    document.getElementById('completeForm').reset();
    document.getElementById('completeInterviewId').value = id;
    // Reset all star displays
    document.querySelectorAll('.star-group').forEach(function (group) {
      var key = group.dataset.key;
      document.getElementById('rating_' + key).value = 0;
      group.querySelectorAll('.star-btn').forEach(function (btn) {
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-gray-300');
      });
    });
    openModal('completeModal');
  };

  /* ─────────────────────── Star Ratings ───────────────────── */
  window.setRating = function (key, value) {
    document.getElementById('rating_' + key).value = value;
    _paintStars(key, value, false);
  };

  window.hoverStars = function (key, value) {
    _paintStars(key, value, true);
  };

  window.unhoverStars = function (key) {
    var current = parseInt(document.getElementById('rating_' + key).value, 10) || 0;
    _paintStars(key, current, false);
  };

  function _paintStars(key, value, isHover) {
    var group = document.querySelector('.star-group[data-key="' + key + '"]');
    if (!group) return;
    group.querySelectorAll('.star-btn').forEach(function (btn) {
      var v = parseInt(btn.dataset.value, 10);
      btn.classList.toggle('text-amber-400', v <= value && !isHover);
      btn.classList.toggle('text-amber-300', v <= value && isHover);
      btn.classList.toggle('text-gray-300', v > value);
      if (v > value) btn.classList.remove('text-amber-300');
    });
  }

  window.submitComplete = function (e) {
    e.preventDefault();
    var id = document.getElementById('completeInterviewId').value;
    var fd = new FormData(e.target);
    var body = {};
    fd.forEach(function (v, k) { body[k] = v; });

    body.id = id;
    fetch('/api/v1/hr-interviews?action=complete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
      if (res.ok && res.data.ok) {
        showToast('Interview marked as complete', 'success');
        closeModal('completeModal');
        setTimeout(function () { location.reload(); }, 900);
      } else {
        showToast(res.data.message || 'Failed to submit feedback', 'error');
      }
    })
    .catch(function () { showToast('Network error. Please try again.', 'error'); });
  };

  /* ──────────────────────── Status Filter ────────────────────── */
  window.filterByStatus = function (status) {
    var rows = document.querySelectorAll('#listView tbody tr');
    rows.forEach(function (row) {
      if (status === 'all' || row.dataset.status === status) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
    document.querySelectorAll('.status-filter-btn').forEach(function (btn) {
      btn.classList.remove('bg-violet-600', 'text-white');
      btn.classList.add('bg-gray-100', 'text-gray-600');
    });
    var activeBtn = document.getElementById('filter-' + status);
    if (activeBtn) {
      activeBtn.classList.remove('bg-gray-100', 'text-gray-600');
      activeBtn.classList.add('bg-violet-600', 'text-white');
    }
  };

  /* ──────────────────────── Export ───────────────────────────── */
  window.exportInterviews = function () {
    window.location = '/api/v1/hr-interviews?action=export';
  };

})();
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
