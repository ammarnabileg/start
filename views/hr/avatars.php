<?php
/**
 * HeyGen avatar management — available HeyGen avatars + company avatars.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
        </span>
        <?= e(app_lang('AI Interviewer Avatars')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Pick lifelike HeyGen avatars to conduct your AI video interviews.</p>
    </div>
    <a href="/settings" class="btn-ghost self-start sm:self-auto">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      API Settings
    </a>
  </div>

  <!-- Section: Available HeyGen Avatars -->
  <section class="mb-10">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold text-gray-900">Available HeyGen Avatars</h2>
      <button id="reload-heygen" class="text-sm text-violet-600 hover:underline inline-flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Refresh
      </button>
    </div>
    <div id="heygen-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5"></div>
    <div id="heygen-notice" class="hidden"></div>
  </section>

  <!-- Section: Company Avatars -->
  <section>
    <div class="flex items-center gap-2 mb-4">
      <h2 class="text-lg font-bold text-gray-900">Company Avatars</h2>
      <span class="badge badge-violet" id="company-count">0</span>
    </div>
    <div id="company-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5"></div>
    <div id="company-empty" class="hidden card p-10 text-center">
      <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      </div>
      <p class="text-gray-900 font-semibold">No company avatars yet</p>
      <p class="text-gray-500 text-sm mt-1">Save an avatar from the HeyGen library above to assign it to your video interview jobs.</p>
    </div>
  </section>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6 max-w-2xl">
    <div class="flex items-start justify-between mb-4">
      <h3 id="preview-title" class="text-lg font-bold text-gray-900">Avatar preview</h3>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="preview-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <div id="preview-body" class="rounded-2xl bg-gray-900 overflow-hidden aspect-video flex items-center justify-center text-gray-300"></div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // HeyGen + company endpoints both return {avatars:[...], warning?} via controller.
  function unwrapAvatars(d) {
    if (Array.isArray(d)) return { list: d, warning: null };
    if (d && Array.isArray(d.avatars)) return { list: d.avatars, warning: d.warning || null };
    if (d && Array.isArray(d.data)) return { list: d.data, warning: d.warning || null };
    if (d && d.error) return { list: [], warning: d.error };
    return { list: [], warning: null };
  }

  function avatarId(a) { return a.avatar_id || a.heygen_avatar_id || a.id; }
  function avatarName(a) { return a.avatar_name || a.name || a.pose_name || ('Avatar ' + (avatarId(a) || '')); }
  function previewUrl(a) { return a.preview_image_url || a.preview_url || a.normal_preview || a.image_url || ''; }

  function placeholder(name) {
    const init = (name || '?').split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
    return '<div class="w-full h-full gradient-brand flex items-center justify-center text-white text-3xl font-bold">' + AR.esc(init) + '</div>';
  }

  function imgOrPlaceholder(url, name, extra) {
    if (url) {
      return '<img src="' + AR.esc(url) + '" alt="' + AR.esc(name) + '" class="w-full h-full object-cover" ' + (extra || '') +
        ' onerror="this.parentNode.innerHTML=\'' + placeholder(name).replace(/'/g, '&#39;') + '\'" />';
    }
    return placeholder(name);
  }

  // ---- HeyGen library ----
  function heygenSkeleton() {
    $('heygen-grid').innerHTML = Array.from({ length: 8 }).map(() =>
      '<div class="card overflow-hidden"><div class="skeleton aspect-[3/4] w-full"></div><div class="p-3 space-y-2"><div class="skeleton h-4 w-3/4"></div><div class="skeleton h-8 w-full"></div></div></div>').join('');
    $('heygen-notice').classList.add('hidden');
    $('heygen-grid').classList.remove('hidden');
  }

  function notice(msg) {
    $('heygen-grid').classList.add('hidden');
    $('heygen-grid').innerHTML = '';
    const n = $('heygen-notice');
    n.classList.remove('hidden');
    n.innerHTML = '<div class="card p-8 text-center border-dashed">' +
      '<div class="mx-auto w-14 h-14 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mb-4">' +
        '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>' +
      '</div>' +
      '<p class="text-gray-900 font-semibold">' + AR.esc(msg) + '</p>' +
      '<p class="text-gray-500 text-sm mt-1">Connect your HeyGen API key in Settings to load the avatar library.</p>' +
      '<a href="/settings" class="btn-primary mt-5 inline-flex">Go to Settings</a>' +
    '</div>';
  }

  function heygenCard(a) {
    const id = avatarId(a), name = avatarName(a), url = previewUrl(a);
    return '<div class="card overflow-hidden group">' +
      '<div class="aspect-[3/4] bg-gray-100 relative overflow-hidden">' + imgOrPlaceholder(url, name) +
        '<button class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center opacity-0 group-hover:opacity-100" data-preview="' + AR.esc(id) + '" data-name="' + AR.esc(name) + '" data-url="' + AR.esc(url) + '">' +
          '<span class="bg-white/90 rounded-full px-3 py-1.5 text-xs font-semibold text-gray-800 inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Preview</span>' +
        '</button>' +
      '</div>' +
      '<div class="p-3">' +
        '<div class="font-semibold text-sm text-gray-900 truncate" title="' + AR.esc(name) + '">' + AR.esc(name) + '</div>' +
        '<div class="text-xs text-gray-400 truncate mb-2">' + AR.esc(a.gender || a.language || 'HeyGen avatar') + '</div>' +
        '<button class="btn-primary w-full justify-center !py-1.5 text-xs" data-save="' + AR.esc(id) + '" data-name="' + AR.esc(name) + '" data-url="' + AR.esc(url) + '" data-voice="' + AR.esc(a.voice_id || a.default_voice_id || '') + '">' +
          '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Save to Company</button>' +
      '</div>' +
    '</div>';
  }

  async function loadHeyGen() {
    heygenSkeleton();
    try {
      const { list, warning } = unwrapAvatars(await AR.Api.get('/avatars/heygen'));
      if (warning && !list.length) { notice(warning); return; }
      if (!list.length) { notice('No HeyGen avatars available'); return; }
      $('heygen-notice').classList.add('hidden');
      $('heygen-grid').classList.remove('hidden');
      $('heygen-grid').innerHTML = list.map(heygenCard).join('');
      bindHeygen();
    } catch (e) {
      notice(e.message || 'Could not reach HeyGen');
    }
  }

  function bindHeygen() {
    $('heygen-grid').querySelectorAll('[data-save]').forEach(btn => btn.addEventListener('click', () => saveAvatar(btn)));
    $('heygen-grid').querySelectorAll('[data-preview]').forEach(btn => btn.addEventListener('click', () => openPreviewStatic(btn.getAttribute('data-name'), btn.getAttribute('data-url'))));
  }

  async function saveAvatar(btn) {
    btn.disabled = true; const old = btn.innerHTML; btn.textContent = 'Saving…';
    try {
      await AR.Api.post('/avatars', {
        heygen_avatar_id: btn.getAttribute('data-save'),
        name: btn.getAttribute('data-name'),
        preview_url: btn.getAttribute('data-url'),
        voice_id: btn.getAttribute('data-voice') || null
      });
      AR.Toast.success('Avatar saved to company.');
      loadCompany();
    } catch (e) {
      AR.Toast.error(e.message || 'Could not save avatar.');
    } finally {
      btn.disabled = false; btn.innerHTML = old;
    }
  }

  // ---- Company avatars ----
  function companySkeleton() {
    $('company-grid').innerHTML = Array.from({ length: 4 }).map(() =>
      '<div class="card overflow-hidden"><div class="skeleton aspect-[3/4] w-full"></div><div class="p-3 space-y-2"><div class="skeleton h-4 w-3/4"></div><div class="skeleton h-8 w-full"></div></div></div>').join('');
    $('company-empty').classList.add('hidden');
  }

  function companyCard(a) {
    const name = a.name || ('Avatar ' + a.id);
    const url = a.preview_url || '';
    const active = Number(a.is_active) === 1 || a.is_active === true || a.is_active === undefined;
    return '<div class="card overflow-hidden group">' +
      '<div class="aspect-[3/4] bg-gray-100 relative overflow-hidden">' + imgOrPlaceholder(url, name) +
        '<span class="absolute top-2 left-2 badge ' + (active ? 'badge-green' : 'badge-gray') + '">' + (active ? 'Active' : 'Inactive') + '</span>' +
      '</div>' +
      '<div class="p-3">' +
        '<div class="font-semibold text-sm text-gray-900 truncate" title="' + AR.esc(name) + '">' + AR.esc(name) + '</div>' +
        '<div class="text-xs text-gray-400 mb-1 flex items-center gap-1">' +
          '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>' +
          AR.esc((a.language || 'en').toUpperCase()) + '</div>' +
        '<p class="text-[11px] text-violet-600 mb-2 flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Assignable to jobs</p>' +
        '<div class="flex gap-2">' +
          '<button class="btn-ghost flex-1 justify-center !py-1.5 text-xs" data-cprev="' + AR.esc(a.id) + '" data-name="' + AR.esc(name) + '" data-url="' + AR.esc(url) + '">Preview</button>' +
          '<button class="btn-ghost !py-1.5 !px-3 text-xs !text-red-600 !border-red-200 hover:!bg-red-50" data-remove="' + AR.esc(a.id) + '"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  async function loadCompany() {
    companySkeleton();
    try {
      const { list } = unwrapAvatars(await AR.Api.get('/avatars'));
      $('company-count').textContent = list.length;
      if (!list.length) {
        $('company-grid').innerHTML = '';
        $('company-empty').classList.remove('hidden');
        return;
      }
      $('company-empty').classList.add('hidden');
      $('company-grid').innerHTML = list.map(companyCard).join('');
      bindCompany();
    } catch (e) {
      $('company-grid').innerHTML = '<div class="col-span-full text-center py-8 text-red-600">' + AR.esc(e.message || 'Could not load company avatars.') + '</div>';
    }
  }

  function bindCompany() {
    $('company-grid').querySelectorAll('[data-remove]').forEach(btn => btn.addEventListener('click', () => removeAvatar(btn.getAttribute('data-remove'))));
    $('company-grid').querySelectorAll('[data-cprev]').forEach(btn => btn.addEventListener('click', () => previewCompany(btn)));
  }

  async function removeAvatar(id) {
    try {
      await AR.Api.del('/avatars/' + encodeURIComponent(id));
      AR.Toast.success('Avatar removed.');
      loadCompany();
    } catch (e) {
      AR.Toast.error(e.message || 'Could not remove avatar.');
    }
  }

  function openPreviewStatic(name, url) {
    $('preview-title').textContent = name || 'Avatar preview';
    $('preview-body').innerHTML = url
      ? '<img src="' + AR.esc(url) + '" alt="' + AR.esc(name) + '" class="w-full h-full object-contain" />'
      : '<div class="text-sm text-gray-400 p-8 text-center">No still image available for this avatar.</div>';
    AR.Modal.open('preview-modal');
  }

  async function previewCompany(btn) {
    const id = btn.getAttribute('data-cprev');
    $('preview-title').textContent = btn.getAttribute('data-name') || 'Avatar preview';
    $('preview-body').innerHTML = '<div class="text-sm text-gray-300 p-8 text-center"><span class="spin inline-block">⏳</span><br>Generating preview video&hellip;</div>';
    AR.Modal.open('preview-modal');
    try {
      const res = await AR.Api.post('/avatars/' + encodeURIComponent(id) + '/preview');
      const videoUrl = (res && (res.video_url || res.url || (res.video && res.video.video_url))) || btn.getAttribute('data-url');
      if (videoUrl && /\.(mp4|webm|mov)(\?|$)/i.test(videoUrl)) {
        $('preview-body').innerHTML = '<video src="' + AR.esc(videoUrl) + '" controls autoplay class="w-full h-full object-contain"></video>';
      } else if (videoUrl) {
        $('preview-body').innerHTML = '<img src="' + AR.esc(videoUrl) + '" class="w-full h-full object-contain" />';
      } else {
        $('preview-body').innerHTML = '<div class="text-sm text-gray-300 p-8 text-center">Preview is being processed. Check back shortly.</div>';
      }
    } catch (e) {
      const fallback = btn.getAttribute('data-url');
      $('preview-body').innerHTML = fallback
        ? '<img src="' + AR.esc(fallback) + '" class="w-full h-full object-contain" />'
        : '<div class="text-sm text-gray-300 p-8 text-center">' + AR.esc(e.message || 'Preview unavailable.') + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('reload-heygen').addEventListener('click', loadHeyGen);
    loadHeyGen();
    loadCompany();
  });
})();
</script>
