/* ===========================================================================
 * وضع التعلّم (Interactive Learning Tour) — AIPush / Watad
 * جولة تفاعلية احترافية بشركة ديمو مليانة بيانات. كل التعديلات في localStorage
 * (الكاش) فقط — لا تمسّ أي بيانات حقيقية. يتخطّى في أي وقت ويُستأنف.
 * ===========================================================================*/
(function () {
  'use strict';
  var ONB = window.ONB || {};
  var LANG = ONB.lang === 'ar' ? 'ar' : 'en';
  var RTL = LANG === 'ar';
  var LS_DONE = 'wtd_tour_done_v1';
  var LS_DEMO = 'wtd_tour_demo_v1_' + LANG; // كاش منفصل لكل لغة حتى لا تظهر لغة قديمة بعد التبديل
  var LS_STEP = 'wtd_tour_step_v1';

  function T(ar, en) { return LANG === 'ar' ? ar : en; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function num(n) { return Number(n || 0).toLocaleString(LANG === 'ar' ? 'ar-EG' : 'en-US'); }
  function money(n) { return (LANG === 'ar' ? '' : '$') + num(Math.round(n)) + (LANG === 'ar' ? ' ر.س' : ''); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }

  /* ---------------------------------------------------------------------------
   * بيانات شركة الديمو — ضخمة ومليانة أرقام وتفاصيل
   * -------------------------------------------------------------------------*/
  function seed() {
    return {
      profile: {
        name: T('وتد للحلول التقنية', 'Watad Technology Solutions'),
        country: 'SA', activity: T('الأمن السيبراني والتحول الرقمي', 'Cybersecurity & Digital Transformation'),
        founded: 2013, employees: 180, hq: T('الرياض — المنطقة الوسطى', 'Riyadh — Central Region'),
        branches: 4, clients: 312, nps: 71,
        tagline: T('شريكك الموثوق في الأمن السيبراني والأتمتة الصناعية', 'Your trusted partner in cybersecurity & industrial automation'),
        about: T('شركة سعودية متخصّصة في الأمن السيبراني (IT/OT)، الأتمتة الصناعية، البنية التحتية، والذكاء الاصطناعي للمنشآت الكبرى في الطاقة والصناعة والقطاع الحكومي.',
          'A Saudi firm specialized in IT/OT cybersecurity, industrial automation, infrastructure and AI for large enterprises in energy, industry and government.')
      },
      services: [
        { name: T('أمن التقنيات التشغيلية (OT)', 'OT Security'), price: 420000, deals: 38, demand: 94 },
        { name: T('جدار حماية الجيل القادم (NGFW)', 'Next-Gen Firewall'), price: 180000, deals: 54, demand: 88 },
        { name: T('مركز العمليات الأمنية (SOC)', 'Security Operations Center'), price: 650000, deals: 21, demand: 81 },
        { name: T('إدارة الأحداث (SIEM)', 'SIEM'), price: 300000, deals: 33, demand: 76 },
        { name: T('الأتمتة الصناعية (SCADA)', 'Industrial Automation (SCADA)'), price: 540000, deals: 19, demand: 72 }
      ],
      audience: [
        { who: T('مدير أمن المعلومات (CISO)', 'CISO'), pain: T('تهديدات OT وامتثال NCA', 'OT threats & NCA compliance'), weight: 92 },
        { who: T('مدير العمليات الصناعية', 'Industrial Ops Manager'), pain: T('توقّف الإنتاج والمخاطر', 'Downtime & risk'), weight: 78 },
        { who: T('الرئيس التنفيذي للتقنية (CTO)', 'CTO'), pain: T('التحول الرقمي الآمن', 'Secure digital transformation'), weight: 65 }
      ],
      channels: [
        { k: 'linkedin', name: 'LinkedIn', on: true, followers: 48200, growth: 6.4 },
        { k: 'x', name: 'X (Twitter)', on: true, followers: 21500, growth: 3.1 },
        { k: 'facebook', name: 'Facebook', on: false, followers: 0, growth: 0 },
        { k: 'instagram', name: 'Instagram', on: true, followers: 15800, growth: 8.9 },
        { k: 'whatsapp', name: 'WhatsApp Business', on: true, followers: 0, growth: 0 },
        { k: 'youtube', name: 'YouTube', on: false, followers: 0, growth: 0 }
      ],
      tenders: [
        { t: T('توريد وتركيب جدران حماية — أرامكو', 'Firewall supply & install — Aramco'), v: 2400000, days: 9, fit: 95 },
        { t: T('مركز عمليات أمنية — وزارة الطاقة', 'SOC build — Ministry of Energy'), v: 5800000, days: 21, fit: 88 },
        { t: T('تحديث SCADA — سابك', 'SCADA upgrade — SABIC'), v: 3100000, days: 14, fit: 82 }
      ],
      competitors: [
        { name: 'SecureNet KSA', followers: 61000, posts30: 24, eng: 3.8, rank: 2 },
        { name: 'CyberGulf', followers: 39500, posts30: 31, eng: 2.9, rank: 3 },
        { name: T('وتد (أنت)', 'Watad (You)'), followers: 48200, posts30: 28, eng: 4.6, rank: 1, you: true }
      ],
      posts: [
        { id: 1, title: T('من يراقب شبكة الـ SCADA ليلاً؟', 'Who watches your SCADA at night?'), status: 'approved', ch: ['linkedin', 'x'], when: '', eng: 0 },
        { id: 2, title: T('النسخ الاحتياطي ليس خطة', 'Backup isn\'t a plan'), status: 'scheduled', ch: ['linkedin'], when: '2026-11-27 10:00', eng: 0 },
        { id: 3, title: T('٧٠٪ من الاختراقات تبدأ من الطرف', '70% of breaches start at the edge'), status: 'published', ch: ['linkedin', 'x', 'instagram'], when: '2026-11-20 09:30', eng: 5.2 }
      ],
      queue: [
        { day: T('الإثنين', 'Mon'), items: 2 }, { day: T('الثلاثاء', 'Tue'), items: 1 }, { day: T('الأربعاء', 'Wed'), items: 3 },
        { day: T('الخميس', 'Thu'), items: 2 }, { day: T('الجمعة', 'Fri'), items: 0 }, { day: T('السبت', 'Sat'), items: 1 }, { day: T('الأحد', 'Sun'), items: 2 }
      ],
      ads: [
        { name: T('حملة OT Security — الربع الرابع', 'OT Security — Q4'), spend: 42000, impr: 1240000, clicks: 18400, leads: 310, roas: 4.2 },
        { name: T('إعادة استهداف SOC', 'SOC Retargeting'), spend: 18500, impr: 540000, clicks: 9200, leads: 142, roas: 5.8 },
        { name: T('توعية NGFW', 'NGFW Awareness'), spend: 27000, impr: 980000, clicks: 12600, leads: 96, roas: 2.4 }
      ],
      analytics: {
        reach: 3820000, engagement: 4.6, followers: 85500, leads: 548, mql: 196, pipeline: 14200000, winRate: 27,
        trend: [42, 55, 48, 63, 71, 69, 84, 91, 88, 97, 112, 128]
      }
    };
  }
  function demo() { return ls(LS_DEMO) || seed(); }
  function saveDemo(d) { ls(LS_DEMO, d); }

  /* ---------------------------------------------------------------------------
   * الفصول (Chapters)
   * -------------------------------------------------------------------------*/
  var chapters = [
    {
      key: 'welcome', icon: '🚀', title: T('ابدأ من هنا', 'Start here'),
      intro: T('أهلاً بك! هذه جولة تفاعلية على شركة ديمو حقيقية البيانات. كل ما تضيفه أو تعدّله هنا محفوظ عندك فقط (في المتصفّح) ولا يؤثّر على أي بيانات حقيقية. تقدر تتخطّى الجولة في أي لحظة.',
        'Welcome! This is an interactive tour on a fully-populated demo company. Anything you add or edit stays only in your browser and never touches real data. You can skip anytime.'),
      render: function (d) {
        return '<div class="onb-hero">'
          + '<div class="onb-hero-badge">' + T('شركة الديمو', 'Demo company') + '</div>'
          + '<h2>' + esc(d.profile.name) + '</h2>'
          + '<p>' + esc(d.profile.tagline) + '</p>'
          + '<div class="onb-kpis">'
          + kpi('🏢', num(d.profile.employees), T('موظف', 'Employees'))
          + kpi('🤝', num(d.profile.clients), T('عميل', 'Clients'))
          + kpi('🌍', num(d.profile.branches), T('فروع', 'Branches'))
          + kpi('⭐', d.profile.nps, 'NPS')
          + '</div></div>'
          + '<div class="onb-form">'
          + '<div class="onb-field"><label>' + T('الدولة', 'Country') + '</label>'
          + '<select data-act="setcountry" class="onb-input"><option value="SA"' + (d.profile.country === 'SA' ? ' selected' : '') + '>' + T('السعودية', 'Saudi Arabia') + '</option><option value="AE"' + (d.profile.country === 'AE' ? ' selected' : '') + '>' + T('الإمارات', 'UAE') + '</option><option value="EG"' + (d.profile.country === 'EG' ? ' selected' : '') + '>' + T('مصر', 'Egypt') + '</option><option value="QA"' + (d.profile.country === 'QA' ? ' selected' : '') + '>' + T('قطر', 'Qatar') + '</option></select></div>'
          + '<div class="onb-field"><label>' + T('نشاط الشركة', 'Business activity') + '</label>'
          + '<input data-act="setactivity" class="onb-input" value="' + esc(d.profile.activity) + '"></div>'
          + '</div>'
          + '<div class="onb-note">' + T('غيّر الدولة والنشاط فوق — الجولة كلها هتتبنى عليهم. (كله في الكاش)', 'Change the country and activity above — the whole tour builds on them. (cached locally)') + '</div>';
      }
    },
    {
      key: 'kb', icon: '📚', title: T('قاعدة المعرفة', 'Knowledge Base'),
      intro: T('قلب النظام. هنا تعرّف الذكاء الاصطناعي على شركتك: نبذة، الخدمات وأسعارها، والجمهور. كل ما تكتبه هنا يستخدمه الذكاء في كتابة المحتوى والردود. جرّب تعدّل سعر خدمة أو تضيف واحدة.',
        'The heart of the system. Here you teach the AI about your company: an overview, services with prices, and audience. Everything here feeds AI copy and replies. Try editing a service price or adding one.'),
      render: function (d) {
        var rows = d.services.map(function (s, i) {
          return '<tr><td>' + esc(s.name) + '</td>'
            + '<td><input data-act="svcprice" data-i="' + i + '" class="onb-cell" value="' + s.price + '"></td>'
            + '<td>' + num(s.deals) + '</td>'
            + '<td>' + bar(s.demand) + '</td>'
            + '<td><button class="onb-x" data-act="svcdel" data-i="' + i + '">✕</button></td></tr>';
        }).join('');
        return '<div class="onb-card"><div class="onb-card-h"><b>' + T('نبذة الشركة', 'Company overview') + '</b></div>'
          + '<textarea data-act="setabout" class="onb-input" rows="3">' + esc(d.profile.about) + '</textarea></div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>' + T('الخدمات والأسعار', 'Services & pricing') + '</b><button class="onb-add" data-act="svcadd">+ ' + T('أضف خدمة', 'Add service') + '</button></div>'
          + '<table class="onb-table"><thead><tr><th>' + T('الخدمة', 'Service') + '</th><th>' + T('السعر', 'Price') + '</th><th>' + T('صفقات', 'Deals') + '</th><th>' + T('الطلب', 'Demand') + '</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>' + T('الجمهور المستهدف', 'Target audience') + '</b></div>'
          + d.audience.map(function (a) { return '<div class="onb-aud"><span class="onb-pill">' + esc(a.who) + '</span><span class="onb-aud-pain">' + esc(a.pain) + '</span>' + bar(a.weight) + '</div>'; }).join('') + '</div>';
      }
    },
    {
      key: 'integrations', icon: '🔌', title: T('التكاملات', 'Integrations'),
      intro: T('اربط قنواتك ومصادرك. من هنا توصّل حسابات التواصل، الواتساب، والبريد. جرّب توصّل أو تفصل قناة — شوف المتابعين والنمو.',
        'Connect your channels and sources: social accounts, WhatsApp and email. Try connecting or disconnecting a channel — see followers and growth.'),
      render: function (d) {
        return '<div class="onb-grid2">' + d.channels.map(function (c, i) {
          return '<div class="onb-ch ' + (c.on ? 'on' : '') + '"><div class="onb-ch-top"><b>' + esc(c.name) + '</b>'
            + '<button class="onb-toggle ' + (c.on ? 'on' : '') + '" data-act="chtoggle" data-i="' + i + '">' + (c.on ? T('متصل', 'Connected') : T('توصيل', 'Connect')) + '</button></div>'
            + (c.on && c.followers ? '<div class="onb-ch-stats"><span>👥 ' + num(c.followers) + '</span><span class="onb-up">▲ ' + c.growth + '%</span></div>' : '<div class="onb-ch-stats onb-muted">' + (c.on ? T('نشط', 'Active') : T('غير متصل', 'Not connected')) + '</div>') + '</div>';
        }).join('') + '</div>';
      }
    },
    {
      key: 'pages', icon: '🧭', title: T('الصفحات المرتبطة', 'Linked pages'),
      intro: T('الفرص والسوق: المناقصات، الشركات المستهدفة، العملاء، والمنافسون. النظام يرصد كل ده تلقائيًا. شوف المناقصات اللي تناسبك ومقارنة المنافسين.',
        'Market & opportunities: tenders, target companies, customers and competitors — tracked automatically. See matching tenders and a competitor comparison.'),
      render: function (d) {
        var tr = d.tenders.map(function (t) { return '<tr><td>' + esc(t.t) + '</td><td>' + money(t.v) + '</td><td><span class="onb-badge">' + T('يُغلق خلال', 'closes in') + ' ' + t.days + T(' يوم', 'd') + '</span></td><td>' + bar(t.fit) + '</td></tr>'; }).join('');
        var cr = d.competitors.slice().sort(function (a, b) { return a.rank - b.rank; }).map(function (c) {
          return '<tr class="' + (c.you ? 'onb-you' : '') + '"><td>#' + c.rank + '</td><td>' + esc(c.name) + '</td><td>' + num(c.followers) + '</td><td>' + c.posts30 + '</td><td>' + c.eng + '%</td></tr>';
        }).join('');
        return '<div class="onb-card"><div class="onb-card-h"><b>🏛️ ' + T('مناقصات تناسبك', 'Matching tenders') + '</b><span class="onb-badge green">' + num(d.tenders.length) + ' ' + T('جديدة', 'new') + '</span></div>'
          + '<table class="onb-table"><tbody>' + tr + '</tbody></table></div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>👁️ ' + T('مقارنة المنافسين', 'Competitor comparison') + '</b></div>'
          + '<table class="onb-table"><thead><tr><th>#</th><th>' + T('المنافس', 'Competitor') + '</th><th>' + T('متابعون', 'Followers') + '</th><th>' + T('منشورات/٣٠ي', 'Posts/30d') + '</th><th>' + T('تفاعل', 'Eng.') + '</th></tr></thead><tbody>' + cr + '</tbody></table></div>';
      }
    },
    {
      key: 'content', icon: '✍️', title: T('إنشاء المحتوى', 'Content creation'),
      intro: T('اكتب هدفك والذكاء يبني خطة محتوى كاملة: موضوع، نص طويل وقصير، برومبت صورة وفيديو وكاروسيل لكل منشور. جرّب تكتب هدف وتضغط توليد (ديمو).',
        'Write your goal and the AI builds a full plan: topic, long & short copy, image/video/carousel prompts per post. Try typing a goal and hit generate (demo).'),
      render: function (d) {
        var pr = (d.posts || []).map(function (p) {
          return '<div class="onb-post"><div class="onb-post-h"><b>' + esc(p.title) + '</b><span class="onb-badge ' + statusColor(p.status) + '">' + statusLabel(p.status) + '</span></div>'
            + '<div class="onb-chips">' + (p.ch || []).map(function (c) { return '<span class="onb-chip">' + c + '</span>'; }).join('') + '</div></div>';
        }).join('');
        return '<div class="onb-card"><div class="onb-card-h"><b>' + T('هدف الحملة', 'Campaign goal') + '</b></div>'
          + '<input id="onb-goal" class="onb-input" placeholder="' + T('مثال: الوصول لمدراء الأمن في قطاع الطاقة وحجز عروض OT', 'e.g. Reach security managers in energy and book OT demos') + '">'
          + '<button class="onb-add" style="margin-top:10px" data-act="gen">✨ ' + T('ولّد خطة المحتوى', 'Generate content plan') + '</button></div>'
          + '<div id="onb-gen" class="onb-posts">' + pr + '</div>';
      }
    },
    {
      key: 'publish', icon: '📅', title: T('النشر والجدولة', 'Publishing & scheduling'),
      intro: T('اعتمد المحتوى وجدوله. التقويم يوزّع المنشورات على القنوات في أفضل الأوقات. شوف الطابور الأسبوعي — جرّب تضيف منشور ليوم.',
        'Approve and schedule content. The calendar spreads posts across channels at the best times. See the weekly queue — try adding a post to a day.'),
      render: function (d) {
        return '<div class="onb-card"><div class="onb-card-h"><b>' + T('الطابور الأسبوعي', 'Weekly queue') + '</b><span class="onb-badge">' + num(d.queue.reduce(function (s, q) { return s + q.items; }, 0)) + ' ' + T('مجدول', 'scheduled') + '</span></div>'
          + '<div class="onb-week">' + d.queue.map(function (q, i) {
            return '<div class="onb-day"><div class="onb-day-n">' + esc(q.day) + '</div><div class="onb-day-dots">' + (q.items ? Array.apply(null, { length: q.items }).map(function () { return '<span class="onb-dot"></span>'; }).join('') : '<span class="onb-muted">—</span>') + '</div><button class="onb-day-add" data-act="qadd" data-i="' + i + '">+</button></div>';
          }).join('') + '</div></div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>' + T('توجيه النص تلقائيًا', 'Automatic copy routing') + '</b></div>'
          + '<div class="onb-route"><div class="onb-route-r"><span class="onb-chip">LinkedIn · ' + T('منصات طويلة', 'long-form') + '</span> ← ' + T('النص الطويل', 'Long copy') + '</div>'
          + '<div class="onb-route-r"><span class="onb-chip">X · ' + T('منصات قصيرة', 'short-form') + '</span> ← ' + T('النص القصير', 'Short copy') + '</div></div></div>';
      }
    },
    {
      key: 'ads', icon: '📣', title: T('الإعلانات', 'Ads'),
      intro: T('أطلق وتابع الحملات المموّلة. النظام يحسب الإنفاق والوصول والعملاء المحتملين وعائد الإنفاق (ROAS). شوف أداء الحملات الحقيقي.',
        'Launch and track paid campaigns. The system computes spend, reach, leads and ROAS. See live campaign performance.'),
      render: function (d) {
        var total = d.ads.reduce(function (a, c) { return { spend: a.spend + c.spend, leads: a.leads + c.leads }; }, { spend: 0, leads: 0 });
        var rows = d.ads.map(function (a) {
          return '<tr><td>' + esc(a.name) + '</td><td>' + money(a.spend) + '</td><td>' + num(a.impr) + '</td><td>' + num(a.clicks) + '</td><td>' + num(a.leads) + '</td><td><b class="' + (a.roas >= 4 ? 'onb-up' : a.roas < 3 ? 'onb-down' : '') + '">' + a.roas + 'x</b></td></tr>';
        }).join('');
        return '<div class="onb-kpis onb-kpis-row">' + kpi('💰', money(total.spend), T('إجمالي الإنفاق', 'Total spend')) + kpi('🎯', num(total.leads), T('عملاء محتملون', 'Leads')) + kpi('📈', (total.leads ? money(total.spend / total.leads) : '—'), T('تكلفة العميل', 'Cost / lead')) + '</div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>' + T('الحملات', 'Campaigns') + '</b></div>'
          + '<table class="onb-table"><thead><tr><th>' + T('الحملة', 'Campaign') + '</th><th>' + T('إنفاق', 'Spend') + '</th><th>' + T('ظهور', 'Impr.') + '</th><th>' + T('نقرات', 'Clicks') + '</th><th>' + T('عملاء', 'Leads') + '</th><th>ROAS</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
      }
    },
    {
      key: 'analytics', icon: '📊', title: T('التحليلات', 'Analytics'),
      intro: T('كل أرقامك في مكان واحد: الوصول، التفاعل، النمو، والعملاء من المحتوى. النظام يربط المحتوى بالإيرادات (Pipeline). شوف الاتجاه.',
        'All your numbers in one place: reach, engagement, growth and content-sourced leads. The system links content to pipeline. See the trend.'),
      render: function (d) {
        var a = d.analytics;
        return '<div class="onb-kpis onb-kpis-row">' + kpi('📡', num(a.reach), T('الوصول', 'Reach')) + kpi('❤️', a.engagement + '%', T('التفاعل', 'Engagement')) + kpi('👥', num(a.followers), T('متابعون', 'Followers')) + kpi('🎯', num(a.leads), T('عملاء', 'Leads')) + '</div>'
          + '<div class="onb-card"><div class="onb-card-h"><b>' + T('نمو الوصول (١٢ شهر)', 'Reach growth (12 months)') + '</b></div>' + spark(a.trend) + '</div>'
          + '<div class="onb-kpis onb-kpis-row">' + kpi('🧲', num(a.mql), 'MQL') + kpi('💼', money(a.pipeline), T('قيمة الفرص', 'Pipeline')) + kpi('🏆', a.winRate + '%', T('نسبة الإغلاق', 'Win rate')) + '</div>';
      }
    },
    {
      key: 'mcp', icon: '🤖', title: T('ربط MCP', 'MCP Connection'),
      intro: T('اربط ChatGPT أو Claude بلوحتك مباشرة عبر MCP — فيشغّلوا النظام نيابةً عنك بالأوامر: "اكتب خطة محتوى"، "جدول المنشورات"، "اعرض التحليلات". دي قوة احترافية.',
        'Connect ChatGPT or Claude straight to your panel via MCP — so they can run the system for you by command: "write a content plan", "schedule the posts", "show analytics". A pro-level capability.'),
      render: function (d) {
        return '<div class="onb-card"><div class="onb-card-h"><b>' + T('نقطة اتصال MCP', 'MCP endpoint') + '</b><span class="onb-badge green">' + T('نشط', 'Active') + '</span></div>'
          + '<div class="onb-code" dir="ltr">https://shohad.com/mcp</div>'
          + '<div class="onb-code onb-code-sm" dir="ltr">token: wtd_demo_••••••••••••7f3a <button class="onb-copy" data-act="copytoken">' + T('نسخ', 'Copy') + '</button></div>'
          + '<div class="onb-mcp-cmds"><div class="onb-mcp-cmd">💬 "' + T('اكتب لي ١٠ منشورات عن أمن OT', 'Write me 10 posts about OT security') + '"</div>'
          + '<div class="onb-mcp-cmd">💬 "' + T('جدول المعتمد على LinkedIn و X', 'Schedule approved to LinkedIn & X') + '"</div>'
          + '<div class="onb-mcp-cmd">💬 "' + T('اعرض أداء آخر ٣٠ يوم', 'Show the last 30 days performance') + '"</div></div></div>'
          + '<div class="onb-done">🎉 ' + T('خلصت الجولة! دلوقتي تعرف كل جزء في النظام.', 'Tour complete! You now know every part of the system.') + '</div>';
      }
    }
  ];

  /* ---------- أدوات رسم ---------- */
  function kpi(ic, v, l) { return '<div class="onb-kpi"><div class="onb-kpi-ic">' + ic + '</div><div class="onb-kpi-v">' + v + '</div><div class="onb-kpi-l">' + esc(l) + '</div></div>'; }
  function bar(p) { return '<span class="onb-bar"><span style="width:' + Math.max(4, Math.min(100, p)) + '%"></span></span>'; }
  function statusColor(s) { return s === 'published' ? 'green' : s === 'scheduled' ? 'blue' : ''; }
  function statusLabel(s) { return s === 'published' ? T('منشور', 'Published') : s === 'scheduled' ? T('مجدول', 'Scheduled') : T('معتمد', 'Approved'); }
  function spark(arr) {
    var max = Math.max.apply(null, arr), w = 100 / arr.length;
    return '<div class="onb-spark">' + arr.map(function (v) { return '<span class="onb-spark-b" style="height:' + (v / max * 100) + '%;width:' + (w - 1) + '%" title="' + num(v * 1000) + '"></span>'; }).join('') + '</div>';
  }

  /* ---------------------------------------------------------------------------
   * المحرّك (Engine)
   * -------------------------------------------------------------------------*/
  var cur = 0, root = null;
  function open(fromStart) {
    cur = fromStart ? 0 : (ls(LS_STEP) || 0); if (cur >= chapters.length) cur = 0;
    if (!ls(LS_DEMO)) saveDemo(seed());
    if (root) root.remove();
    root = document.createElement('div');
    root.id = 'onb-root'; root.dir = RTL ? 'rtl' : 'ltr';
    document.body.appendChild(root); document.body.style.overflow = 'hidden';
    paint();
  }
  function close() { if (root) { root.remove(); root = null; } document.body.style.overflow = ''; }
  function finish() { ls(LS_DONE, 1); ls(LS_STEP, null); close(); }
  function go(i) { cur = Math.max(0, Math.min(chapters.length - 1, i)); ls(LS_STEP, cur); paint(); }

  function paint() {
    var d = demo(), ch = chapters[cur];
    var steps = chapters.map(function (c, i) {
      return '<button class="onb-step ' + (i === cur ? 'on' : i < cur ? 'done' : '') + '" data-go="' + i + '" title="' + esc(c.title) + '"><span class="onb-step-ic">' + c.icon + '</span><span class="onb-step-t">' + esc(c.title) + '</span></button>';
    }).join('');
    root.innerHTML = ''
      + '<div class="onb-ov"></div>'
      + '<div class="onb-shell">'
      + '  <div class="onb-top">'
      + '    <div class="onb-brand"><span class="onb-logo">🎓</span><b>' + T('وضع التعلّم', 'Learning Mode') + '</b><span class="onb-demo-tag">' + T('بيانات ديمو', 'Demo data') + '</span></div>'
      + '    <div class="onb-top-actions"><button class="onb-reset" data-act="resetdemo">↺ ' + T('تصفير الديمو', 'Reset demo') + '</button><button class="onb-skip" data-act="skip">' + T('تخطّي الجولة', 'Skip tour') + ' ✕</button></div>'
      + '  </div>'
      + '  <div class="onb-steps">' + steps + '</div>'
      + '  <div class="onb-progress"><span style="width:' + ((cur + 1) / chapters.length * 100) + '%"></span></div>'
      + '  <div class="onb-body">'
      + '    <div class="onb-coach"><div class="onb-coach-ic">' + ch.icon + '</div><div><div class="onb-coach-t">' + esc(ch.title) + '</div><div class="onb-coach-x">' + esc(ch.intro) + '</div></div></div>'
      + '    <div class="onb-stage">' + ch.render(d) + '</div>'
      + '  </div>'
      + '  <div class="onb-nav">'
      + '    <button class="onb-btn ghost" data-act="back" ' + (cur === 0 ? 'disabled' : '') + '>' + (RTL ? '→ ' : '← ') + T('السابق', 'Back') + '</button>'
      + '    <div class="onb-nav-mid">' + (cur + 1) + ' / ' + chapters.length + '</div>'
      + '    <button class="onb-btn primary" data-act="next">' + (cur === chapters.length - 1 ? T('إنهاء 🎉', 'Finish 🎉') : T('التالي', 'Next') + (RTL ? ' ←' : ' →')) + '</button>'
      + '  </div>'
      + '</div>';
  }

  /* ---------- التفاعلات ---------- */
  function onClick(e) {
    if (!root) return;
    var b = e.target.closest('[data-act],[data-go]'); if (!b) return;
    if (b.hasAttribute('data-go')) return go(+b.getAttribute('data-go'));
    var a = b.getAttribute('data-act'), d = demo();
    if (a === 'skip') return finish();
    if (a === 'back') return go(cur - 1);
    if (a === 'next') return cur === chapters.length - 1 ? finish() : go(cur + 1);
    if (a === 'resetdemo') { saveDemo(seed()); return paint(); }
    if (a === 'svcdel') { d.services.splice(+b.getAttribute('data-i'), 1); saveDemo(d); return paint(); }
    if (a === 'svcadd') { d.services.push({ name: T('خدمة جديدة', 'New service'), price: 100000, deals: 0, demand: 50 }); saveDemo(d); return paint(); }
    if (a === 'chtoggle') { var c = d.channels[+b.getAttribute('data-i')]; c.on = !c.on; if (c.on && !c.followers && c.k !== 'whatsapp' && c.k !== 'youtube') { c.followers = 1000 + Math.round(Math.abs(hash(c.k)) % 40000); c.growth = 2 + (Math.abs(hash(c.k)) % 80) / 10; } saveDemo(d); return paint(); }
    if (a === 'qadd') { d.queue[+b.getAttribute('data-i')].items++; saveDemo(d); return paint(); }
    if (a === 'gen') { return runGen(d); }
    if (a === 'copytoken') { try { navigator.clipboard.writeText('wtd_demo_tour_token'); } catch (x) {} b.textContent = T('تم ✓', 'Copied ✓'); return; }
  }
  function onInput(e) {
    if (!root) return; var b = e.target, a = b.getAttribute && b.getAttribute('data-act'); if (!a) return; var d = demo();
    if (a === 'setcountry') { d.profile.country = b.value; saveDemo(d); }
    else if (a === 'setactivity') { d.profile.activity = b.value; saveDemo(d); }
    else if (a === 'setabout') { d.profile.about = b.value; saveDemo(d); }
    else if (a === 'svcprice') { var s = d.services[+b.getAttribute('data-i')]; if (s) { s.price = parseInt(b.value, 10) || 0; saveDemo(d); } }
  }
  function runGen(d) {
    var goal = (root.querySelector('#onb-goal') || {}).value || '';
    var box = root.querySelector('#onb-gen'); if (!box) return;
    box.innerHTML = '<div class="onb-genload"><span class="onb-spin"></span> ' + T('الذكاء يكتب خطة المحتوى…', 'AI is writing the content plan…') + '</div>';
    var ideas = [
      T('٣ أخطاء تترك شبكة OT مكشوفة', '3 mistakes that leave OT exposed'),
      T('كم يكلّفك توقّف إنتاج لساعة؟', 'What does one hour of downtime cost you?'),
      T('NGFW مش رفاهية — هو خط الدفاع الأول', 'NGFW isn\'t a luxury — it\'s your first line'),
      T('كيف يكشف الـ SOC الاختراق في دقائق', 'How a SOC catches a breach in minutes')
    ];
    setTimeout(function () {
      box.innerHTML = '<div class="onb-gen-head">✅ ' + T('تم توليد', 'Generated') + ' ' + ideas.length + ' ' + T('منشورات' + (goal ? ' لهدف: ' + esc(goal.slice(0, 40)) : ''), 'posts' + (goal ? ' for: ' + esc(goal.slice(0, 40)) : '')) + '</div>'
        + ideas.map(function (t, i) {
          return '<div class="onb-post"><div class="onb-post-h"><b>' + esc(t) + '</b><span class="onb-badge">' + T('مسودة', 'Draft') + '</span></div><div class="onb-chips"><span class="onb-chip">' + T('نص طويل', 'Long') + '</span><span class="onb-chip">' + T('نص قصير', 'Short') + '</span><span class="onb-chip">' + T('صورة', 'Image') + '</span><span class="onb-chip">' + T('كاروسيل', 'Carousel') + '</span></div></div>';
        }).join('');
    }, 900);
  }
  function hash(s) { var h = 0; for (var i = 0; i < s.length; i++) { h = (h << 5) - h + s.charCodeAt(i); h |= 0; } return h; }

  document.addEventListener('click', onClick);
  document.addEventListener('input', onInput);
  document.addEventListener('keydown', function (e) {
    if (!root) return;
    if (e.key === 'Escape') finish();
    else if (e.key === 'ArrowRight') go(RTL ? cur - 1 : cur + 1);
    else if (e.key === 'ArrowLeft') go(RTL ? cur + 1 : cur - 1);
  });

  // زر فتح الجولة + تشغيل تلقائي أول مرة
  window.wtdTour = function () { open(true); };
  var btn = document.getElementById('onb-launch');
  if (btn) btn.addEventListener('click', function () { open(true); });
  if (ONB.auto && !ls(LS_DONE)) { setTimeout(function () { open(false); }, 700); }
})();
