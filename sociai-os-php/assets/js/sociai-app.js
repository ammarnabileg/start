/**
 * SociAI OS — Application Engine
 * Pure Vanilla JS · No external dependencies
 * @version 1.0.0
 */

'use strict';

/* ══════════════════════════════════════════════
   SOCIAI NAMESPACE
══════════════════════════════════════════════ */
const SociAI = window.SociAI || {};

Object.assign(SociAI, {
  version: '1.0.0',
  apiBase: '/api',
  _loadingCount: 0,

  get csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  },

  /* ── Init ─────────────────────────────────── */
  init() {
    this.initSidebar();
    this.initTheme();
    this.initNotifications();
    this.initUserMenu();
    this.initSearch();
    this.initModals();
    this.initToastContainer();
    this.initTabs();
    this.initForms();
    this.initCharts();
    this.initAgentCards();
    this.initCopywritingStudio();
    this.initTrendsPage();
    this.initCommunityQueue();
    this.initPlatformConnect();
    this.initUploadZones();
    this.initSettingsTabs();
    this.initPasswordStrength();
    this.initDashboard();
    this.initCampaigns();
    this.initTeamPage();
    this.initContentCalendar();
    console.log(`%c⚡ SociAI OS v${this.version}`, 'color:#3B82F6;font-weight:800;font-size:14px;');
  },

  /* ── Sidebar ──────────────────────────────── */
  initSidebar() {
    const sidebar = document.querySelector('.sg-sidebar');
    const main    = document.querySelector('.sg-main');
    const colBtn  = document.querySelector('.sg-collapse-btn');
    if (!sidebar) return;

    const saved = localStorage.getItem('sg_collapsed') === 'true';
    if (saved) { sidebar.classList.add('collapsed'); main?.classList.add('expanded'); }

    colBtn?.addEventListener('click', () => {
      const c = sidebar.classList.toggle('collapsed');
      main?.classList.toggle('expanded', c);
      localStorage.setItem('sg_collapsed', c);
    });

    // Mobile overlay
    const overlay = Object.assign(document.createElement('div'), {
      className: 'sg-sidebar-overlay',
    });
    Object.assign(overlay.style, {
      position: 'fixed', inset: '0', background: 'rgba(0,0,0,0.6)',
      zIndex: '199', display: 'none', backdropFilter: 'blur(2px)',
    });
    document.body.appendChild(overlay);

    document.querySelector('.sg-mobile-toggle')?.addEventListener('click', () => {
      sidebar.classList.add('mobile-open');
      overlay.style.display = 'block';
    });
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      overlay.style.display = 'none';
    });

    // Active state
    const path = window.location.pathname;
    document.querySelectorAll('.sg-nav-item[data-href]').forEach(item => {
      if (path.includes(item.dataset.href)) item.classList.add('active');
      item.addEventListener('click', () => {
        if (item.dataset.href) window.location.href = item.dataset.href;
      });
    });
  },

  /* ── Theme ────────────────────────────────── */
  initTheme() {
    document.querySelectorAll('.sg-lang-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.sg-lang-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const lang = btn.dataset.lang;
        const isAr = lang === 'ar';
        document.body.classList.toggle('rtl', isAr);
        document.documentElement.setAttribute('dir', isAr ? 'rtl' : 'ltr');
        document.documentElement.setAttribute('lang', lang || 'en');
        localStorage.setItem('sg_lang', lang);
      });
    });
    const savedLang = localStorage.getItem('sg_lang');
    if (savedLang) document.querySelector(`.sg-lang-btn[data-lang="${savedLang}"]`)?.click();

    document.querySelector('.sg-theme-toggle')?.addEventListener('click', function() {
      const next = document.body.dataset.theme === 'light' ? 'dark' : 'light';
      document.body.dataset.theme = next;
      this.textContent = next === 'light' ? '☀️' : '🌙';
      localStorage.setItem('sg_theme', next);
    });
  },

  /* ── Notifications ────────────────────────── */
  initNotifications() {
    const bell = document.querySelector('.sg-notif');
    const dd   = document.querySelector('.sg-notif-dd');
    if (!bell || !dd) return;
    bell.addEventListener('click', e => {
      e.stopPropagation();
      dd.classList.toggle('open');
      document.querySelector('.sg-user-dd')?.classList.remove('open');
    });
    document.querySelector('.sg-mark-read')?.addEventListener('click', () => {
      document.querySelectorAll('.sg-notif-item.unread').forEach(i => i.classList.remove('unread'));
      document.querySelector('.sg-notif-dot')?.remove();
      this.showToast('All notifications marked as read', 'success');
    });
    document.addEventListener('click', e => {
      if (!bell.contains(e.target)) dd.classList.remove('open');
    });
  },

  /* ── User Menu ────────────────────────────── */
  initUserMenu() {
    const menu = document.querySelector('.sg-user');
    const dd   = document.querySelector('.sg-user-dd');
    if (!menu || !dd) return;
    menu.addEventListener('click', e => { e.stopPropagation(); dd.classList.toggle('open'); });
    document.addEventListener('click', () => dd.classList.remove('open'));
  },

  /* ── Search ───────────────────────────────── */
  initSearch() {
    const input = document.querySelector('.sg-search input');
    if (!input) return;
    const results = [
      { t: 'Dashboard',          href: '/dashboard',            i: '📊' },
      { t: 'Copywriting Studio', href: '/dashboard/copywriting', i: '✍️' },
      { t: 'AI Agents',          href: '/dashboard/agents',      i: '🤖' },
      { t: 'Analytics',          href: '/dashboard/analytics',   i: '📈' },
      { t: 'Trend Hunter',       href: '/dashboard/trends',      i: '🔥' },
      { t: 'Community',          href: '/dashboard/community',   i: '💬' },
      { t: 'Campaigns',          href: '/dashboard/campaigns',   i: '🎯' },
      { t: 'Settings',           href: '/dashboard/settings',    i: '⚙️' },
    ];
    const dd = document.createElement('div');
    Object.assign(dd.style, {
      position: 'absolute', top: 'calc(100% + 8px)', left: '0', right: '0',
      background: 'var(--navy-mid)', border: '1px solid var(--glass-border)',
      borderRadius: 'var(--radius-md)', boxShadow: 'var(--shadow-lg)',
      zIndex: '500', display: 'none', overflow: 'hidden',
    });
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(dd);

    const doSearch = this.debounce(v => {
      if (!v.trim()) { dd.style.display = 'none'; return; }
      const hits = results.filter(r => r.t.toLowerCase().includes(v.toLowerCase()));
      if (!hits.length) { dd.style.display = 'none'; return; }
      dd.innerHTML = hits.map(r =>
        `<a href="${r.href}" style="display:flex;align-items:center;gap:.5rem;padding:.7rem 1rem;font-size:.85rem;color:var(--text-secondary);transition:background .2s"
           onmouseover="this.style.background='var(--glass-bg)'" onmouseout="this.style.background=''">${r.i} ${r.t}</a>`
      ).join('');
      dd.style.display = 'block';
    }, 200);

    input.addEventListener('input', e => doSearch(e.target.value));
    input.addEventListener('keydown', e => { if (e.key === 'Escape') { dd.style.display = 'none'; input.blur(); } });
    document.addEventListener('click', e => { if (!input.parentElement.contains(e.target)) dd.style.display = 'none'; });
  },

  /* ── API Call ─────────────────────────────── */
  async apiCall(endpoint, method = 'GET', data = null) {
    this._loadingCount++;
    document.querySelector('.sg-global-loader')?.setAttribute('style', 'display:block');
    const cfg = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
    };
    if (data && method !== 'GET') cfg.body = JSON.stringify(data);
    try {
      const res  = await fetch(`${this.apiBase}${endpoint}`, cfg);
      const json = await res.json();
      if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
      return json;
    } catch (err) {
      this.showToast(err.message || 'Request failed', 'error');
      throw err;
    } finally {
      if (--this._loadingCount === 0) {
        document.querySelector('.sg-global-loader')?.setAttribute('style', 'display:none');
      }
    }
  },

  /* ── Content Generation ───────────────────── */
  async generateContent(type = 'caption') {
    const btn    = document.querySelector('.sg-gen-btn, .generate-btn');
    const loader = document.querySelector('.sg-ai-load, .ai-loading');
    const output = document.querySelector('.sg-output, .output-box');

    if (btn) { btn.disabled = true; btn.classList.add('sg-btn-loading', 'btn-loading'); }
    if (loader) loader.classList.add('active', 'visible');
    if (output) output.innerHTML = '';

    await this._sleep(500 + Math.random() * 700);

    const samples = {
      caption:  `✨ Transform your vision into reality.\n\nEvery great achievement starts with a bold decision. Today, we're sharing how our team turned an ambitious idea into a product used by 50,000+ professionals worldwide.\n\nThe secret? We never stopped asking: "How can we do this better?"\n\n💡 What's one bold decision you've made this week?\n\n#Innovation #Entrepreneurship #Growth #Leadership #Success`,
      linkedin: `I used to think scaling a business was about working harder.\n\nI was wrong.\n\nAfter 3 years and building 2 companies from scratch, here's what I've learned:\n\n→ Systems beat hustle every time\n→ Your team IS your product\n→ Customer obsession compounds\n→ Say no to good so you can say yes to great\n\nThe counterintuitive truth: The less I did, the faster we grew.\n\nWhat's the best business lesson you've learned the hard way?\n\n#Entrepreneurship #Leadership #BusinessGrowth #StartupLife`,
      thread:   `🧵 Thread: How we grew from 0 to 100K followers in 6 months (without ads)\n\n1/ The strategy everyone ignores...\n\n2/ We posted 3x/day for 90 days with data-driven timing.\n\n3/ Every piece of content answered ONE specific question.\n\n4/ We engaged 50 comments/day in our niche BEFORE posting.\n\n5/ Result: 847% growth, 12% avg engagement rate.\n\nRetweet if this helped 🔁`,
      hook:     `"I deleted my social media for 30 days. Here's what happened to my business..."\n\n"The LinkedIn post I almost didn't publish got 2.3M impressions."\n\n"Stop posting content. Start publishing assets."\n\n"I analyzed 1,000 viral posts. They all had ONE thing in common."`,
      script:   `[HOOK - 0:00-0:05]\nHold on — before you scroll, this changed everything.\n\n[PROBLEM - 0:05-0:20]\nI was posting every single day and getting nowhere.\n\n[SOLUTION - 0:20-0:45]\nThen I discovered the ONE framework that changed everything — the Value-Hook-CTA method.\n\n[CTA - 0:45-0:60]\nFollow for Part 2. Drop a 🔥 if you want the template.`,
    };
    const text = samples[type] || samples.caption;

    if (loader) loader.classList.remove('active', 'visible');
    if (btn) { btn.disabled = false; btn.classList.remove('sg-btn-loading', 'btn-loading'); }

    if (output) {
      output.innerHTML = '';
      await this._typewriter(output, text, 10);
      this.showToast('Content generated!', 'success');
    }
    return text;
  },

  async _typewriter(el, text, speed = 12) {
    let i = 0;
    return new Promise(resolve => {
      const tick = () => {
        if (i < text.length) {
          el.textContent += text[i++];
          el.scrollTop = el.scrollHeight;
          setTimeout(tick, speed + Math.random() * speed * 0.4);
        } else resolve();
      };
      tick();
    });
  },

  /* ── Toast ────────────────────────────────── */
  initToastContainer() {
    if (!document.querySelector('.sg-toast-container')) {
      const c = document.createElement('div');
      c.className = 'sg-toast-container';
      document.body.appendChild(c);
    }
  },

  showToast(msg, type = 'info', ms = 4000) {
    const c = document.querySelector('.sg-toast-container');
    if (!c) return;
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const t = document.createElement('div');
    t.className = `sg-toast sg-toast-${type}`;
    t.innerHTML = `<span>${icons[type]||icons.info}</span>
      <div style="flex:1;font-size:.85rem">${msg}</div>
      <button onclick="this.closest('.sg-toast').remove()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem">×</button>`;
    c.appendChild(t);
    t.addEventListener('click', () => { t.classList.add('leaving'); setTimeout(() => t.remove(), 300); });
    setTimeout(() => { t.classList.add('leaving'); setTimeout(() => t.remove(), 300); }, ms);
  },

  /* ── Charts ───────────────────────────────── */
  initCharts() {
    this._drawLineChart();
    this._drawBarChart();
    this._drawDonutCharts();
  },

  _drawLineChart() {
    const canvas = document.getElementById('reachChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 600, H = canvas.offsetHeight || 200;
    canvas.width = W; canvas.height = H;
    const data   = [12000,18500,15200,22000,28000,24500,31000,27800,35000,41000,38000,47000];
    const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const pad = { t: 20, r: 20, b: 30, l: 52 };
    const cW = W - pad.l - pad.r, cH = H - pad.t - pad.b;
    const max = Math.max(...data) * 1.1;
    const xP = i => pad.l + (i / (data.length-1)) * cW;
    const yP = v => pad.t + cH - (v / max) * cH;
    ctx.clearRect(0, 0, W, H);
    // Grid
    ctx.strokeStyle = 'rgba(255,255,255,0.06)'; ctx.lineWidth = 1;
    for (let i=0; i<=4; i++) {
      const y = pad.t + (i/4)*cH;
      ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
      ctx.fillStyle = 'rgba(148,163,184,0.6)'; ctx.font = '10px system-ui'; ctx.textAlign = 'right';
      ctx.fillText(SociAI.formatNumber(max-(i/4)*max), pad.l-6, y+4);
    }
    // Fill
    const g = ctx.createLinearGradient(0,pad.t,0,H-pad.b);
    g.addColorStop(0,'rgba(59,130,246,0.3)'); g.addColorStop(1,'rgba(59,130,246,0)');
    ctx.beginPath(); ctx.moveTo(xP(0),yP(data[0]));
    data.forEach((v,i) => i>0 && ctx.lineTo(xP(i),yP(v)));
    ctx.lineTo(xP(data.length-1),H-pad.b); ctx.lineTo(xP(0),H-pad.b);
    ctx.closePath(); ctx.fillStyle = g; ctx.fill();
    // Line
    ctx.beginPath(); ctx.moveTo(xP(0),yP(data[0]));
    for (let i=1; i<data.length; i++) {
      const cpx = (xP(i-1)+xP(i))/2;
      ctx.bezierCurveTo(cpx,yP(data[i-1]),cpx,yP(data[i]),xP(i),yP(data[i]));
    }
    ctx.strokeStyle='#3B82F6'; ctx.lineWidth=2.5; ctx.stroke();
    // Dots
    data.forEach((v,i) => {
      ctx.beginPath(); ctx.arc(xP(i),yP(v),4,0,Math.PI*2);
      ctx.fillStyle='#3B82F6'; ctx.fill();
      ctx.strokeStyle='#0A0B1A'; ctx.lineWidth=2; ctx.stroke();
    });
    // X labels
    ctx.fillStyle='rgba(148,163,184,0.7)'; ctx.font='10px system-ui'; ctx.textAlign='center';
    labels.forEach((l,i) => ctx.fillText(l,xP(i),H-pad.b+16));
  },

  _drawBarChart() {
    const canvas = document.getElementById('engagementChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 500, H = canvas.offsetHeight || 220;
    canvas.width = W; canvas.height = H;
    const platforms = [
      { n:'LinkedIn',  v:8.4,  c:'#3B82F6' },
      { n:'Instagram', v:12.7, c:'#EC4899' },
      { n:'TikTok',    v:18.2, c:'#EF4444' },
      { n:'Facebook',  v:5.1,  c:'#60A5FA' },
      { n:'Twitter/X', v:4.8,  c:'#7DD3FC' },
      { n:'YouTube',   v:7.3,  c:'#FCA5A5' },
    ];
    const pad={t:15,r:20,b:20,l:82}, cW=W-pad.l-pad.r, cH=H-pad.t-pad.b;
    const max=20, barH=Math.min((cH/platforms.length)*0.6,24), gap=cH/platforms.length;
    ctx.clearRect(0,0,W,H);
    platforms.forEach((p,i) => {
      const y = pad.t + i*gap + (gap-barH)/2;
      const bW = (p.v/max)*cW;
      ctx.fillStyle='rgba(255,255,255,0.05)';
      this._rrect(ctx,pad.l,y,cW,barH,barH/2); ctx.fill();
      const g2=ctx.createLinearGradient(pad.l,0,pad.l+bW,0);
      g2.addColorStop(0,p.c); g2.addColorStop(1,p.c+'99');
      ctx.fillStyle=g2; this._rrect(ctx,pad.l,y,bW,barH,barH/2); ctx.fill();
      ctx.fillStyle='rgba(148,163,184,0.8)'; ctx.font='11px system-ui'; ctx.textAlign='right';
      ctx.fillText(p.n,pad.l-6,y+barH/2+4);
      ctx.fillStyle='#F0F4FF'; ctx.textAlign='left';
      ctx.fillText(`${p.v}%`,pad.l+bW+6,y+barH/2+4);
    });
  },

  _rrect(ctx,x,y,w,h,r) {
    ctx.beginPath(); ctx.moveTo(x+r,y); ctx.lineTo(x+w-r,y);
    ctx.quadraticCurveTo(x+w,y,x+w,y+r); ctx.lineTo(x+w,y+h-r);
    ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h); ctx.lineTo(x+r,y+h);
    ctx.quadraticCurveTo(x,y+h,x,y+h-r); ctx.lineTo(x,y+r);
    ctx.quadraticCurveTo(x,y,x+r,y); ctx.closePath();
  },

  _drawDonutCharts() {
    document.querySelectorAll('[data-sg-donut]').forEach(chart => {
      try {
        const segs = JSON.parse(chart.dataset.sgDonut);
        const svg  = chart.querySelector('svg');
        if (!svg) return;
        const r = 70, cx = 90, cy = 90, circ = 2*Math.PI*r;
        let offset = 0;
        svg.innerHTML = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="18"/>`;
        segs.forEach(s => {
          const dash = (s.pct/100)*circ;
          const el = document.createElementNS('http://www.w3.org/2000/svg','circle');
          el.setAttribute('cx',cx); el.setAttribute('cy',cy); el.setAttribute('r',r);
          el.setAttribute('fill','none'); el.setAttribute('stroke',s.color);
          el.setAttribute('stroke-width','18');
          el.setAttribute('stroke-dasharray',`${dash} ${circ-dash}`);
          el.setAttribute('stroke-dashoffset',-offset*circ/100);
          el.setAttribute('stroke-linecap','round');
          svg.appendChild(el);
          offset += s.pct;
        });
      } catch(e) {}
    });
    // Also support old data-segments format
    document.querySelectorAll('.donut-chart[data-segments]').forEach(chart => {
      try {
        const segs = JSON.parse(chart.dataset.segments);
        const svg  = chart.querySelector('.donut-svg');
        if (!svg) return;
        const r = 70, cx = 90, cy = 90, circ = 2*Math.PI*r;
        let offset = 0;
        svg.innerHTML = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="18"/>`;
        segs.forEach(s => {
          const dash = (s.pct/100)*circ;
          const el = document.createElementNS('http://www.w3.org/2000/svg','circle');
          el.setAttribute('cx',cx); el.setAttribute('cy',cy); el.setAttribute('r',r);
          el.setAttribute('fill','none'); el.setAttribute('stroke',s.color);
          el.setAttribute('stroke-width','18');
          el.setAttribute('stroke-dasharray',`${dash} ${circ-dash}`);
          el.setAttribute('stroke-dashoffset',-offset*circ/100);
          el.setAttribute('stroke-linecap','round');
          svg.appendChild(el);
          offset += s.pct;
        });
      } catch(e) {}
    });
  },

  /* ── Agent Cards ──────────────────────────── */
  initAgentCards() {
    document.querySelectorAll('.sg-agent, .agent-card').forEach((card, i) => {
      card.addEventListener('click', () => {
        document.querySelectorAll('.sg-agent, .agent-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
      });
    });

    document.querySelector('.run-all-agents')?.addEventListener('click', async function() {
      this.disabled = true; this.classList.add('sg-btn-loading', 'btn-loading');
      SociAI.showToast('Launching all AI agents...', 'info');
      await SociAI._sleep(2000);
      document.querySelectorAll('.sg-status-dot, .status-dot').forEach(d => {
        d.className = d.className.replace(/sg-idle|sg-stopped|sg-error|status-idle|status-stopped/, '') + ' sg-running status-running';
      });
      this.disabled = false; this.classList.remove('sg-btn-loading', 'btn-loading');
      SociAI.showToast('All agents launched!', 'success');
    });

    // Simulate live counter
    setInterval(() => {
      document.querySelectorAll('.sg-running, .status-running').forEach(dot => {
        const c = dot.closest('.sg-agent, .agent-card')?.querySelector('.agent-task-count, .sg-task-count');
        if (c) c.textContent = (parseInt(c.textContent)||0) + Math.floor(Math.random()*2);
      });
    }, 5000);
  },

  /* ── Copywriting Studio ───────────────────── */
  initCopywritingStudio() {
    if (!document.querySelector('.copywriting-studio, .sg-studio')) return;

    document.querySelectorAll('.sg-type-tab, .content-type-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        tab.closest('.sg-type-tabs, .content-type-tabs')
           ?.querySelectorAll('.sg-type-tab, .content-type-tab')
           .forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
      });
    });

    document.querySelectorAll('.style-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        btn.closest('div')?.querySelectorAll('.style-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });

    document.querySelector('.sg-gen-btn, .generate-btn')?.addEventListener('click', () => {
      const type = document.querySelector('.sg-type-tab.active, .content-type-tab.active')?.dataset.type || 'caption';
      SociAI.generateContent(type);
    });

    document.querySelector('.copy-output-btn')?.addEventListener('click', () => {
      const text = document.querySelector('.sg-output, .output-box')?.textContent;
      if (text?.trim()) navigator.clipboard.writeText(text).then(() => SociAI.showToast('Copied!','success'));
      else SociAI.showToast('Nothing to copy','warning');
    });
    document.querySelector('.save-output-btn')?.addEventListener('click', () => SociAI.showToast('Saved to library!','success'));
    document.querySelector('.regen-output-btn')?.addEventListener('click', () => {
      const type = document.querySelector('.sg-type-tab.active, .content-type-tab.active')?.dataset.type || 'caption';
      SociAI.generateContent(type);
    });
  },

  /* ── Trends Page ──────────────────────────── */
  initTrendsPage() {
    if (!document.querySelector('.trends-page')) return;
    document.querySelectorAll('.platform-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.platform-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const platform = btn.dataset.platform;
        document.querySelectorAll('.trend-card, .sg-trend').forEach(card => {
          card.style.display = (platform === 'all' || card.dataset.platform === platform) ? '' : 'none';
        });
      });
    });
    document.querySelectorAll('.gen-trend-btn').forEach(btn => {
      btn.addEventListener('click', async function() {
        this.disabled = true; this.textContent = 'Generating...';
        await SociAI._sleep(1500);
        this.disabled = false; this.textContent = 'Generate Content';
        SociAI.showToast('Trend content created! Check Content Hub.','success');
      });
    });
  },

  /* ── Community Queue ──────────────────────── */
  initCommunityQueue() {
    document.querySelector('.community-queue')?.addEventListener('click', async e => {
      const card = e.target.closest('.reply-card, .sg-reply');
      if (!card) return;
      if (e.target.classList.contains('approve-btn')) {
        e.target.disabled = true; e.target.textContent = '...';
        await SociAI._sleep(600);
        Object.assign(card.style, { transform: 'translateX(20px)', opacity: '0', transition: 'all .3s' });
        setTimeout(() => card.remove(), 300);
        SociAI.showToast('Reply approved & posted!','success');
      }
      if (e.target.classList.contains('skip-btn')) {
        Object.assign(card.style, { transform: 'translateX(-20px)', opacity: '0', transition: 'all .3s' });
        setTimeout(() => card.remove(), 300);
        SociAI.showToast('Skipped','info');
      }
      if (e.target.classList.contains('edit-btn')) {
        const sug = card.querySelector('.ai-suggestion, .sg-ai-suggest');
        if (sug) {
          const txt = prompt('Edit reply:', sug.textContent);
          if (txt) { sug.textContent = txt; SociAI.showToast('Reply updated!','success'); }
        }
      }
    });
  },

  /* ── Platform Connect ─────────────────────── */
  initPlatformConnect() {
    const grid = document.querySelector('.platform-connect-grid, .sg-connect-grid');
    if (!grid) return;
    let count = parseInt(document.querySelector('.connected-count')?.textContent || '0');

    grid.querySelectorAll('.connect-platform-btn').forEach(btn => {
      btn.addEventListener('click', async function() {
        const card = this.closest('.platform-connect-card, .sg-connect-card');
        if (card.classList.contains('connected')) {
          card.classList.remove('connected');
          card.querySelector('.connected-check, .sg-check-badge')?.remove();
          this.textContent = 'Connect';
          this.className = this.className.replace(/btn-success/,'btn-primary');
          count--;
        } else {
          this.disabled = true; this.textContent = 'Connecting...';
          await SociAI._sleep(1200 + Math.random() * 600);
          card.classList.add('connected');
          const badge = Object.assign(document.createElement('div'), {
            className: 'connected-check sg-check-badge', textContent: '✓'
          });
          card.appendChild(badge);
          this.textContent = '✓ Connected';
          this.className = this.className.replace(/btn-primary/,'btn-success');
          this.disabled = false; count++;
          SociAI.showToast(`${card.querySelector('h4')?.textContent} connected!`,'success');
        }
        const cEl = document.querySelector('.connected-count');
        if (cEl) cEl.textContent = count;
        const bar = document.querySelector('.connect-progress-fill');
        if (bar) bar.style.width = `${(count/11)*100}%`;
        const launch = document.querySelector('.launch-sociai-btn');
        if (launch) launch.disabled = count < 1;
      });
    });
  },

  /* ── Upload Zones ─────────────────────────── */
  initUploadZones() {
    document.querySelectorAll('.upload-zone, .sg-upload').forEach(zone => {
      const inp = Object.assign(document.createElement('input'), {
        type: 'file', multiple: true,
        accept: '.pdf,.doc,.docx,.txt,.ppt,.pptx,.jpg,.png,.mp4',
        style: 'display:none',
      });
      zone.appendChild(inp);
      zone.addEventListener('click', () => inp.click());
      zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
      zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
      zone.addEventListener('drop', async e => {
        e.preventDefault(); zone.classList.remove('drag-over');
        await SociAI._processFiles([...e.dataTransfer.files], zone);
      });
      inp.addEventListener('change', async () => { await SociAI._processFiles([...inp.files], zone); });
    });
  },

  async _processFiles(files, zone) {
    const progress = zone.closest('.upload-section')?.querySelector('.upload-progress');
    if (progress) progress.style.display = 'block';
    for (const f of files) {
      SociAI.showToast(`Uploading ${f.name}...`, 'info');
      await SociAI._sleep(900 + Math.random()*1000);
      SociAI.showToast(`${f.name} analyzed!`, 'success');
    }
    if (progress) progress.style.display = 'none';
    const results = zone.closest('.upload-section')?.querySelector('.analysis-results');
    if (results) results.style.display = 'block';
  },

  /* ── Modals ───────────────────────────────── */
  initModals() {
    document.querySelectorAll('[data-modal]').forEach(t => t.addEventListener('click', () => SociAI.openModal(t.dataset.modal)));
    document.querySelectorAll('.sg-modal-overlay, .modal-overlay').forEach(o => {
      o.addEventListener('click', e => { if (e.target === o) SociAI.closeModal(o.id); });
    });
    document.querySelectorAll('.sg-modal-close, .modal-close').forEach(b => {
      b.addEventListener('click', () => {
        const m = b.closest('.sg-modal-overlay, .modal-overlay');
        if (m) SociAI.closeModal(m.id);
      });
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.sg-modal-overlay.open, .modal-overlay.open').forEach(m => SociAI.closeModal(m.id));
      }
    });
  },

  openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
  },

  /* ── Tabs ─────────────────────────────────── */
  initTabs() {
    document.querySelectorAll('.sg-tab-btn, .tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        if (!target) return;
        const scope = btn.closest('.tab-scope') || document;
        scope.querySelectorAll('.sg-tab-btn, .tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        scope.querySelectorAll('.sg-tab-pane, .tab-pane').forEach(p => {
          p.classList.toggle('active', p.dataset.tabPane === target || p.id === target);
        });
      });
    });
  },

  /* ── Forms ────────────────────────────────── */
  initForms() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
      form.addEventListener('submit', e => {
        e.preventDefault();
        if (!this.validateForm(form)) return;
        const btn = form.querySelector('[type="submit"]');
        btn?.classList.add('sg-btn-loading', 'btn-loading');
        if (btn) btn.disabled = true;
        setTimeout(() => {
          btn?.classList.remove('sg-btn-loading', 'btn-loading');
          if (btn) btn.disabled = false;
          SociAI.showToast('Form submitted!', 'success');
        }, 1500);
      });
    });
  },

  validateForm(form) {
    let ok = true;
    form.querySelectorAll('[required]').forEach(field => {
      const g = field.closest('.sg-form-group, .form-group');
      if (!field.value.trim()) {
        g?.classList.add('has-error');
        const err = g?.querySelector('.form-error');
        if (err) err.textContent = 'This field is required.';
        ok = false;
      } else {
        g?.classList.remove('has-error');
      }
    });
    return ok;
  },

  /* ── Settings Tabs ────────────────────────── */
  initSettingsTabs() {
    document.querySelectorAll('.sg-settings-tab, .settings-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.sg-settings-tab, .settings-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const panel = tab.dataset.panel;
        document.querySelectorAll('.sg-settings-panel, .settings-panel').forEach(p => {
          p.style.display = p.id === panel ? 'block' : 'none';
        });
      });
    });
  },

  /* ── Password Strength ────────────────────── */
  initPasswordStrength() {
    document.querySelectorAll('[data-strength]').forEach(input => {
      const g    = input.closest('.sg-form-group, .form-group');
      const bars = g?.querySelectorAll('.sg-strength-bar, .strength-bar');
      const lbl  = g?.querySelector('.sg-strength-lbl, .strength-label');
      if (!bars?.length) return;
      input.addEventListener('input', () => {
        const s = SociAI.getPasswordStrength(input.value);
        const levels = ['','weak','fair','good','strong'];
        const labels = ['','Weak','Fair','Good','Strong'];
        const colors = ['','var(--red)','var(--yellow)','var(--blue)','var(--green)'];
        bars.forEach((b, i) => { b.className = b.className.replace(/\bweak\b|\bfair\b|\bgood\b|\bstrong\b|\bfilled-\w+/g,'').trim() + (i < s ? ` ${levels[s]} filled-${levels[s]}` : ''); });
        if (lbl) { lbl.textContent = labels[s]||''; lbl.style.color = colors[s]||''; }
      });
    });
  },

  getPasswordStrength(v) {
    if (!v) return 0;
    let s = 0;
    if (v.length >= 8)  s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    return Math.min(4, Math.max(1, Math.ceil(s * 0.8)));
  },

  /* ── Dashboard ────────────────────────────── */
  initDashboard() {
    if (!document.querySelector('.dashboard-page')) return;
    document.querySelectorAll('.metric-value[data-target], .sg-metric-val[data-target]').forEach(el => {
      const target = parseFloat(el.dataset.target);
      const prefix = el.dataset.prefix || '';
      const suffix = el.dataset.suffix || '';
      let cur = 0;
      const step = target / 60;
      const t = setInterval(() => {
        cur = Math.min(cur + step, target);
        el.textContent = prefix + SociAI.formatNumber(cur) + suffix;
        if (cur >= target) clearInterval(t);
      }, 16);
    });
  },

  /* ── Content Calendar ─────────────────────── */
  initContentCalendar() {
    const cal = document.querySelector('.content-calendar, .sg-calendar');
    if (!cal) return;
    const now = new Date(), y = now.getFullYear(), m = now.getMonth();
    const days = new Date(y,m+1,0).getDate();
    const firstDay = new Date(y,m,1).getDay();
    const hasPosts = new Set([3,7,10,14,17,21,24,28]);
    let html = '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">';
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
      html += `<div style="text-align:center;font-size:.7rem;font-weight:600;color:var(--text-muted);padding:.3rem">${d}</div>`;
    });
    for (let i=0;i<firstDay;i++) html += '<div></div>';
    for (let d=1;d<=days;d++) {
      const isToday = d === now.getDate();
      const has = hasPosts.has(d);
      html += `<div onclick="SociAI.showToast('Day ${d} — click to schedule a post','info')" style="min-height:46px;padding:.3rem;background:${isToday?'rgba(59,130,246,0.1)':'var(--glass-bg)'};border-radius:6px;cursor:pointer;transition:all .2s;border:1px solid ${isToday?'var(--blue)':'transparent'};position:relative" onmouseover="this.style.borderColor='var(--glass-border-hover)'" onmouseout="this.style.borderColor='${isToday?'var(--blue)':'transparent'}'">
        <span style="font-size:.75rem;font-weight:600">${d}</span>
        ${has?`<span style="width:5px;height:5px;background:var(--green);border-radius:50%;display:block;margin-top:3px"></span>`:''}
      </div>`;
    }
    html += '</div>';
    cal.innerHTML = html;
  },

  /* ── Campaigns ────────────────────────────── */
  initCampaigns() {
    document.querySelector('.create-campaign-btn')?.addEventListener('click', () => SociAI.openModal('createCampaignModal'));
    document.querySelector('.ai-brief-btn')?.addEventListener('click', async function() {
      this.disabled=true; this.classList.add('sg-btn-loading','btn-loading');
      await SociAI._sleep(2000);
      this.disabled=false; this.classList.remove('sg-btn-loading','btn-loading');
      SociAI.showToast('AI Brief generated!','success');
    });
  },

  /* ── Team ─────────────────────────────────── */
  initTeamPage() {
    document.querySelector('.invite-member-btn')?.addEventListener('click', () => SociAI.openModal('inviteMemberModal'));
  },

  /* ── Utilities ────────────────────────────── */
  formatNumber(n) {
    n = parseFloat(n);
    if (n >= 1e9) return (n/1e9).toFixed(1).replace('.0','')+'B';
    if (n >= 1e6) return (n/1e6).toFixed(1).replace('.0','')+'M';
    if (n >= 1e3) return (n/1e3).toFixed(1).replace('.0','')+'K';
    return Math.round(n).toLocaleString();
  },

  timeAgo(d) {
    const s = Math.floor((Date.now()-new Date(d))/1000);
    if (s<60) return `${s}s ago`;
    if (s<3600) return `${Math.floor(s/60)}m ago`;
    if (s<86400) return `${Math.floor(s/3600)}h ago`;
    return `${Math.floor(s/86400)}d ago`;
  },

  debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(()=>fn(...a), ms); }; },
  throttle(fn, ms) { let l=0; return (...a) => { const n=Date.now(); if(n-l>=ms){l=n;fn(...a);} }; },
  _sleep(ms) { return new Promise(r => setTimeout(r, ms)); },
});

/* ── Quick Generate ──────────────────────────── */
document.querySelector('.sg-btn-gen, .btn-quick-gen')?.addEventListener('click', () => {
  SociAI.openModal('quickGenerateModal');
});

/* ── Boot ────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => SociAI.init());

/* ── Redraw on resize ────────────────────────── */
window.addEventListener('resize', SociAI.debounce(() => {
  SociAI._drawLineChart();
  SociAI._drawBarChart();
}, 300));
