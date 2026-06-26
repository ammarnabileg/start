<?php
/**
 * Avatar management (HeyGen). Grid of avatar cards + "New Avatar" modal.
 * Controller may inject: $avatars.
 */
require_once __DIR__ . '/../partials/helpers.php';

// Load real avatars from DB.
if (!isset($avatars) && class_exists('Database') && class_exists('Auth')) {
    $db       = \Database::getInstance();
    $authUser = \Auth::user();
    $tenantId = (int) ($authUser['tenant_id'] ?? 0);
    if ($tenantId > 0) {
        $rows = $db->fetchAll(
            "SELECT av.*, (SELECT COUNT(*) FROM jobs j WHERE j.avatar_id = av.id) AS jobs
             FROM avatars av WHERE av.tenant_id = ? ORDER BY av.created_at DESC",
            [$tenantId]
        ) ?: [];
        if ($rows) { $avatars = $rows; }
    }
}

$avatars = $avatars ?? [
    ['id'=>1,'name'=>'Sophia','gender'=>'female','personality'=>'professional','language'=>'en','jobs'=>4,'is_active'=>1,'heygen_avatar_id'=>'avt_sophia_01','image_url'=>null],
    ['id'=>2,'name'=>'Marcus','gender'=>'male','personality'=>'friendly','language'=>'both','jobs'=>2,'is_active'=>1,'heygen_avatar_id'=>'avt_marcus_02','image_url'=>null],
    ['id'=>3,'name'=>'Layla','gender'=>'female','personality'=>'formal','language'=>'both','jobs'=>1,'is_active'=>1,'heygen_avatar_id'=>'avt_layla_03','image_url'=>null],
    ['id'=>4,'name'=>'David','gender'=>'male','personality'=>'casual','language'=>'en','jobs'=>0,'is_active'=>0,'heygen_avatar_id'=>'avt_david_04','image_url'=>null],
];

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
                    <button onclick="App.toast('Loading preview for <?= e($a['name']) ?>…','info')" class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-lg py-1.5 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 4 20 12 6 20"/></svg>Test
                    </button>
                    <button onclick="App.toast('Editing <?= e($a['name']) ?>','info')" class="flex-1 text-center text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg py-1.5 transition-colors">Edit</button>
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
        <form class="space-y-4" data-validate onsubmit="event.preventDefault(); App.toast('Avatar created','success'); App.closeModal('newAvatarModal');">
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
                <button type="button" onclick="App.toast('Generating preview…','info')" class="inline-flex items-center gap-1.5 text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-full px-3 py-1.5 transition-colors"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="6 4 20 12 6 20"/></svg>Test Preview</button>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" data-modal-close class="rounded-full px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 font-semibold text-sm transition-colors">Save Avatar</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
