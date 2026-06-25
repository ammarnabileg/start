<?php
/**
 * Avatar management (HeyGen). Grid of avatar cards + "New Avatar" modal.
 * Controller may inject: $avatars.
 */
require_once __DIR__ . '/../partials/helpers.php';

$db = Database::getInstance();
$tid = Auth::user()['tenant_id'] ?? 0;
try {
    $avatars = $db->fetchAll(
        "SELECT av.*,
                COUNT(DISTINCT j.id) as jobs
         FROM avatars av
         LEFT JOIN jobs j ON JSON_CONTAINS(j.avatar_ids, CAST(av.id AS JSON)) AND j.tenant_id = ?
         WHERE av.tenant_id = ?
         GROUP BY av.id
         ORDER BY av.created_at DESC",
        [$tid, $tid]
    ) ?: [];
} catch (\Exception $e) {
    // fallback: simple query without job count
    try {
        $avatars = $db->fetchAll("SELECT *, 0 as jobs FROM avatars WHERE tenant_id = ? ORDER BY created_at DESC", [$tid]) ?: [];
    } catch (\Exception $e2) { $avatars = []; }
}

$personaCls = ['professional'=>'bg-violet-100 text-violet-700','friendly'=>'bg-amber-100 text-amber-700','formal'=>'bg-blue-100 text-blue-700','casual'=>'bg-emerald-100 text-emerald-700'];
$langLabel  = ['en'=>'English','ar'=>'Arabic','both'=>'EN / AR'];

$pageTitle   = 'Avatars';
$activeNav   = 'avatars';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Avatars']];

ob_start();
?>
<div class="flex items-center justify-between mb-6">
    <p class="text-gray-500">AI interviewer avatars for your video interviews, powered by HeyGen.</p>
    <button data-modal-open="newAvatarModal" class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 font-semibold text-sm shadow-sm transition-all">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
        New Avatar
    </button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    <?php foreach ($avatars as $a): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">
            <div class="relative aspect-[4/3] bg-gradient-to-br from-violet-500 to-violet-700 flex items-center justify-center">
                <?php if (!empty($a['image_url'])): ?>
                    <img src="<?= e($a['image_url']) ?>" alt="<?= e($a['name']) ?>" loading="lazy" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-4xl font-bold text-white/90"><?= e(initials($a['name'])) ?></span>
                <?php endif; ?>
                <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-semibold <?= $a['is_active']?'bg-emerald-500 text-white':'bg-gray-900/40 text-white' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $a['is_active']?'bg-white':'bg-gray-300' ?>"></span><?= $a['is_active']?'Active':'Inactive' ?>
                </span>
                <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] font-medium bg-black/30 text-white backdrop-blur"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="m16 10 6-3v10l-6-3"/></svg>HeyGen</span>
            </div>
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-gray-900"><?= e($a['name']) ?></h3>
                    <span class="text-xs text-gray-400 capitalize"><?= e($a['gender']) ?></span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-medium capitalize <?= $personaCls[$a['personality']]??'bg-gray-100 text-gray-600' ?>"><?= e($a['personality']) ?></span>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-600"><?= e($langLabel[$a['language']]??$a['language']) ?></span>
                </div>
                <div class="mt-3 flex items-center gap-1.5 text-xs text-gray-500">
                    <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Assigned to <?= (int)$a['jobs'] ?> job<?= $a['jobs']==1?'':'s' ?>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center gap-2">
                    <button onclick="testAvatar(<?= (int)$a['id'] ?>, '<?= e($a['heygen_avatar_id']) ?>')" class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-lg py-1.5 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 4 20 12 6 20"/></svg>Test
                    </button>
                    <button onclick="editAvatar(<?= (int)$a['id'] ?>, <?= json_encode($a) ?>)" class="flex-1 text-center text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg py-1.5 transition-colors">Edit</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Add tile -->
    <button data-modal-open="newAvatarModal" class="group bg-white rounded-2xl border-2 border-dashed border-gray-200 hover:border-violet-300 hover:bg-violet-50/30 transition-colors flex flex-col items-center justify-center p-8 min-h-[260px]">
        <span class="w-12 h-12 rounded-2xl bg-violet-100 text-violet-600 flex items-center justify-center group-hover:scale-105 transition-transform"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
        <span class="mt-3 text-sm font-semibold text-gray-700">Add New Avatar</span>
        <span class="text-xs text-gray-400 mt-1">Connect a HeyGen avatar</span>
    </button>
</div>

<!-- New Avatar modal -->
<div id="newAvatarModal" data-modal class="hidden fixed inset-0 z-[90] flex items-center justify-center p-4">
    <div data-modal-overlay class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-5">
            <h3 class="text-lg font-bold text-gray-900">New Avatar</h3>
            <button data-modal-close class="text-gray-400 hover:text-gray-700"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <form class="space-y-4" data-validate onsubmit="submitNewAvatar(event)">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Name <span class="text-rose-500">*</span></label>
                    <input name="name" required type="text" placeholder="e.g. Sophia" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender</label>
                    <select name="gender" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none"><option value="female">Female</option><option value="male">Male</option><option value="neutral">Neutral</option></select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Personality</label>
                    <select name="personality" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none"><option value="professional">Professional</option><option value="friendly">Friendly</option><option value="formal">Formal</option><option value="casual">Casual</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Language</label>
                    <select name="language" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none"><option value="en">English</option><option value="ar">Arabic</option><option value="both">Both</option></select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5 flex items-center justify-between">HeyGen Avatar ID <a href="https://app.heygen.com" target="_blank" rel="noopener" class="text-xs text-violet-600 hover:text-violet-700">Open HeyGen ↗</a></label>
                <input name="heygen_avatar_id" type="text" placeholder="avt_xxxxxxxx" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">HeyGen Voice ID</label>
                <input name="heygen_voice_id" type="text" placeholder="voice_xxxxxxxx" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none">
            </div>
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 flex items-center justify-between">
                <span class="text-sm text-gray-500">Preview the avatar before saving</span>
                <button type="button" onclick="testAvatarPreview(this)" class="inline-flex items-center gap-1.5 text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-full px-3 py-1.5 transition-colors"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 4 20 12 6 20"/></svg>Test Preview</button>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" data-modal-close class="rounded-full px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 font-semibold text-sm transition-colors">Save Avatar</button>
            </div>
        </form>
    </div>
</div>
<script>
async function submitNewAvatar(e) {
    e.preventDefault();
    var form = e.target;
    var btn = form.querySelector('[type="submit"]');
    btn.disabled = true; btn.textContent = 'Saving…';
    var data = Object.fromEntries(new FormData(form));
    try {
        var res = await fetch('/api/v1/avatars?action=create', {
            method: 'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        var json = await res.json();
        if (json.success) {
            App.toast('Avatar created!', 'success');
            App.closeModal('newAvatarModal');
            setTimeout(function(){ location.reload(); }, 800);
        } else { App.toast(json.message || 'Failed to create avatar', 'error'); }
    } catch(err) { App.toast('Error saving avatar', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Save Avatar'; }
}

async function testAvatar(id, heygenId) {
    if (!heygenId) { App.toast('No HeyGen avatar ID configured', 'warning'); return; }
    App.toast('Testing avatar connection…', 'info');
    try {
        var res = await fetch('/api/v1/avatars?action=test', {
            method: 'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({avatar_id: id, heygen_avatar_id: heygenId})
        });
        var data = await res.json();
        App.toast(data.success ? 'Avatar is connected and working!' : (data.message||'Test failed'), data.success?'success':'error');
    } catch(e) { App.toast('Error testing avatar', 'error'); }
}

function editAvatar(id, avatar) {
    var nameEl  = document.querySelector('[name="name"]');
    var genderEl = document.querySelector('[name="gender"]');
    var persEl  = document.querySelector('[name="personality"]');
    var langEl  = document.querySelector('[name="language"]');
    var hidEl   = document.querySelector('[name="heygen_avatar_id"]');
    if (nameEl)  nameEl.value  = avatar.name || '';
    if (genderEl) genderEl.value = avatar.gender || 'female';
    if (persEl)  persEl.value  = avatar.personality || 'professional';
    if (langEl)  langEl.value  = avatar.language || 'en';
    if (hidEl)   hidEl.value   = avatar.heygen_avatar_id || '';
    var form = nameEl ? nameEl.closest('form') : null;
    if (form) form.dataset.editId = id;
    App.openModal('newAvatarModal');
}

async function testAvatarPreview(btn) {
    var heygenId = btn.closest('form')?.querySelector('[name="heygen_avatar_id"]')?.value;
    if (!heygenId) { App.toast('Enter a HeyGen Avatar ID first', 'warning'); return; }
    App.toast('Testing HeyGen connection for ' + heygenId + '…', 'info');
    try {
        var res = await fetch('/api/v1/avatars?action=test', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({heygen_avatar_id: heygenId})
        });
        var data = await res.json();
        App.toast(data.success ? 'Avatar ID is valid!' : (data.message||'Invalid avatar ID'), data.success?'success':'error');
    } catch(e) { App.toast('Error testing connection', 'error'); }
}
</script>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
