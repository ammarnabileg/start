'use client';

import { setupApi } from '@/lib/api';
import { useEffect, useRef, useState } from 'react';

/* ─── types ─── */
interface SysCheck { status: boolean; value: string }
interface Settings {
  db: { host: string; port: string; name: string; user: string; password: string };
  platform_name: string;
  has_openai_key: boolean;
  has_heygen_key: boolean;
  admin_email: string;
  installed: boolean;
  locked: boolean;
}

const QUICK_CMDS = [
  'php artisan migrate --force',
  'php artisan migrate:fresh --seed',
  'php artisan key:generate',
  'php artisan jwt:secret',
  'php artisan config:clear',
  'php artisan cache:clear',
  'php artisan optimize:clear',
  'php artisan storage:link',
  'php artisan queue:restart',
  'composer install',
];

/* ─── helpers ─── */
function Field({ label, value, onChange, type = 'text', placeholder = '', hint = '', disabled = false }: {
  label: string; value: string; onChange?: (v: string) => void;
  type?: string; placeholder?: string; hint?: string; disabled?: boolean;
}) {
  return (
    <div>
      <label className="block text-xs font-semibold text-slate-300 mb-1">{label}</label>
      <input
        type={type}
        value={value}
        onChange={e => onChange?.(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        className="w-full px-3 py-2.5 text-sm bg-slate-800 border border-slate-600 rounded-lg text-white placeholder-slate-500
          focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 disabled:opacity-40 transition-all"
      />
      {hint && <p className="text-xs text-slate-500 mt-1">{hint}</p>}
    </div>
  );
}

function Section({ title, icon, children }: { title: string; icon: string; children: React.ReactNode }) {
  return (
    <div className="bg-slate-800/60 border border-slate-700 rounded-2xl overflow-hidden">
      <div className="flex items-center gap-2 px-5 py-3.5 bg-slate-700/50 border-b border-slate-700">
        <span className="text-lg">{icon}</span>
        <h2 className="font-bold text-white text-sm">{title}</h2>
      </div>
      <div className="p-5 space-y-4">{children}</div>
    </div>
  );
}

/* ═══════════════════════════════════════════════════════ */
export default function SetupPage() {
  /* ── state ── */
  const [status, setStatus]     = useState<{ installed: boolean; locked: boolean } | null>(null);
  const [sysChecks, setSysChecks] = useState<Record<string, SysCheck>>({});
  const [checksLoading, setChecksLoading] = useState(true);

  const [db, setDb] = useState({ host: '127.0.0.1', port: '3306', name: 'ai_recruitment', user: 'root', password: '' });
  const [admin, setAdmin] = useState({ name: '', email: '', password: '' });
  const [platform, setPlatform] = useState({ name: 'AI Recruit', openai_key: '', heygen_key: '' });

  const [testing, setTesting]   = useState(false);
  const [testResult, setTestResult] = useState<{ ok: boolean; msg: string } | null>(null);
  const [installing, setInstalling] = useState(false);
  const [saving, setSaving]     = useState(false);
  const [done, setDone]         = useState(false);
  const [locking, setLocking]   = useState(false);
  const [locked, setLocked]     = useState(false);

  /* ── terminal ── */
  const [cmd, setCmd]           = useState('');
  const [termHistory, setTermHistory] = useState<{ cmd: string; output: string; ok: boolean }[]>([]);
  const [running, setRunning]   = useState(false);
  const [histIdx, setHistIdx]   = useState(-1);
  const termRef = useRef<HTMLDivElement>(null);

  /* ── init ── */
  useEffect(() => {
    setupApi.status().then(r => {
      setStatus(r.data);
      setLocked(r.data.locked);
      if (r.data.locked) return;
      if (r.data.installed) {
        setDone(true);
        setupApi.settings().then(s => {
          const d = s.data;
          setDb(prev => ({ ...prev, host: d.db.host, port: d.db.port, name: d.db.name, user: d.db.user }));
          setPlatform(prev => ({ ...prev, name: d.platform_name }));
          setAdmin(prev => ({ ...prev, email: d.admin_email }));
        }).catch(() => {});
      }
    }).catch(() => {});

    setupApi.check().then(r => { setSysChecks(r.data); setChecksLoading(false); }).catch(() => setChecksLoading(false));
  }, []);

  useEffect(() => {
    if (termRef.current) termRef.current.scrollTop = termRef.current.scrollHeight;
  }, [termHistory]);

  /* ── actions ── */
  const testDb = async () => {
    setTesting(true); setTestResult(null);
    try {
      const r = await setupApi.testDb({ host: db.host, port: db.port, database: db.name, username: db.user, password: db.password });
      setTestResult({ ok: r.data.success, msg: r.data.message });
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } };
      setTestResult({ ok: false, msg: err?.response?.data?.message || 'فشل الاتصال' });
    } finally { setTesting(false); }
  };

  const install = async () => {
    if (!admin.name || !admin.email || !admin.password || !platform.openai_key) {
      alert('يرجى ملء جميع الحقول المطلوبة (اسم المدير، البريد، كلمة المرور، مفتاح OpenAI)');
      return;
    }
    setInstalling(true);
    try {
      await setupApi.install({ db, admin, ai: { app_name: platform.name, openai_key: platform.openai_key, heygen_key: platform.heygen_key } });
      setDone(true);
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } };
      alert(err?.response?.data?.message || 'فشل التثبيت');
    } finally { setInstalling(false); }
  };

  const save = async () => {
    setSaving(true);
    try {
      await setupApi.update({
        db_host: db.host, db_port: db.port, db_name: db.name, db_user: db.user,
        db_password: db.password || undefined,
        platform_name: platform.name,
        openai_key: platform.openai_key || undefined,
        heygen_key: platform.heygen_key || undefined,
        admin_name: admin.name || undefined,
        admin_email: admin.email || undefined,
        admin_password: admin.password || undefined,
      });
      alert('تم الحفظ بنجاح ✓');
      setAdmin(prev => ({ ...prev, password: '' }));
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } } };
      alert(err?.response?.data?.message || 'فشل الحفظ');
    } finally { setSaving(false); }
  };

  const runCmd = async () => {
    if (!cmd.trim() || running) return;
    const c = cmd.trim();
    setRunning(true);
    setCmd('');
    setHistIdx(-1);
    try {
      const r = await setupApi.terminal(c);
      setTermHistory(h => [...h, { cmd: c, output: r.data.output || '(no output)', ok: r.data.success }]);
    } catch {
      setTermHistory(h => [...h, { cmd: c, output: 'خطأ في الاتصال بالسيرفر', ok: false }]);
    } finally { setRunning(false); }
  };

  const lockSetup = async () => {
    if (!confirm('هل أنت متأكد؟ بعد القفل لن تتمكن من الوصول لصفحة الإعداد إلا بحذف ملف storage/setup.lock من السيرفر.')) return;
    setLocking(true);
    try {
      await setupApi.lock();
      setLocked(true);
    } catch { alert('فشل القفل'); }
    finally { setLocking(false); }
  };

  /* ── keyboard history in terminal ── */
  const onTermKey = (e: React.KeyboardEvent) => {
    const cmds = termHistory.map(h => h.cmd);
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      const idx = histIdx + 1 < cmds.length ? histIdx + 1 : histIdx;
      setHistIdx(idx);
      setCmd(cmds[cmds.length - 1 - idx] || '');
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      const idx = histIdx - 1 >= 0 ? histIdx - 1 : -1;
      setHistIdx(idx);
      setCmd(idx === -1 ? '' : cmds[cmds.length - 1 - idx]);
    } else if (e.key === 'Enter') {
      runCmd();
    }
  };

  const allPass = !checksLoading && Object.values(sysChecks).every(c => c.status);

  /* ─────────── UI ─────────── */

  /* Locked state */
  if (locked) return (
    <div className="min-h-screen bg-[#0f0c29] flex items-center justify-center p-6" dir="rtl">
      <Stars />
      <div className="relative z-10 max-w-md w-full text-center">
        <div className="text-6xl mb-4">🔒</div>
        <h1 className="text-2xl font-bold text-white mb-2">صفحة الإعداد مقفلة</h1>
        <p className="text-slate-400 text-sm leading-relaxed">
          تم قفل صفحة الإعداد لأسباب أمنية.<br />
          لإعادة الفتح، احذف الملف التالي من السيرفر:<br />
          <code className="text-violet-400 mt-2 block text-xs">storage/setup.lock</code>
        </p>
        <a href="/login" className="mt-6 inline-block bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
          الذهاب لتسجيل الدخول
        </a>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-[#0f0c29] pb-16" dir="rtl">
      <Stars />

      <div className="relative z-10 max-w-4xl mx-auto px-4 pt-10">

        {/* ── Header ── */}
        <div className="flex items-center gap-3 mb-8">
          <div className="w-11 h-11 bg-gradient-to-br from-violet-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-violet-500/30 text-xl">⚡</div>
          <div>
            <h1 className="text-xl font-extrabold text-white tracking-tight">إعداد المنصة</h1>
            <p className="text-xs text-slate-400">Setup & Configuration</p>
          </div>
          <div className="mr-auto flex items-center gap-2">
            {done && !locked && (
              <span className="text-xs bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-3 py-1 rounded-full">
                ✓ مُثبَّت
              </span>
            )}
            {done && (
              <a href="/login" className="text-xs bg-violet-600/20 text-violet-300 border border-violet-500/30 px-3 py-1.5 rounded-full hover:bg-violet-600/30 transition-colors">
                تسجيل الدخول ←
              </a>
            )}
          </div>
        </div>

        {/* ── System Status ── */}
        <div className="bg-slate-800/40 border border-slate-700 rounded-2xl p-4 mb-6">
          <p className="text-xs font-semibold text-slate-400 mb-3">فحص متطلبات النظام</p>
          {checksLoading ? (
            <div className="flex gap-2 items-center text-slate-400 text-xs">
              <span className="w-3 h-3 border-2 border-violet-500 border-t-transparent rounded-full animate-spin inline-block" />
              جاري الفحص...
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {Object.entries(sysChecks).map(([k, v]) => (
                <div key={k} className={`flex items-center gap-2 px-3 py-2 rounded-lg text-xs ${v.status ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}`}>
                  <span>{v.status ? '✓' : '✗'}</span>
                  <span className="truncate">{k}</span>
                  <span className="text-slate-500 mr-auto">{v.value}</span>
                </div>
              ))}
            </div>
          )}
          {!checksLoading && !allPass && (
            <p className="text-xs text-red-400 mt-2">⚠ بعض المتطلبات غير مستوفاة. يُفضل إصلاحها قبل المتابعة.</p>
          )}
        </div>

        {/* ── Main 2-col grid ── */}
        <div className="space-y-5">

          {/* Database */}
          <Section title="قاعدة البيانات" icon="🗄️">
            <div className="grid grid-cols-2 gap-4">
              <Field label="المضيف (Host)" value={db.host} onChange={v => setDb(p => ({ ...p, host: v }))} placeholder="127.0.0.1" />
              <Field label="المنفذ (Port)" value={db.port} onChange={v => setDb(p => ({ ...p, port: v }))} placeholder="3306" />
            </div>
            <Field label="اسم قاعدة البيانات" value={db.name} onChange={v => setDb(p => ({ ...p, name: v }))} placeholder="ai_recruitment" />
            <div className="grid grid-cols-2 gap-4">
              <Field label="اسم المستخدم" value={db.user} onChange={v => setDb(p => ({ ...p, user: v }))} placeholder="root" />
              <Field label="كلمة المرور" value={db.password} onChange={v => setDb(p => ({ ...p, password: v }))} placeholder="••••••" type="password"
                hint={done ? 'اتركها فارغة لعدم التغيير' : ''} />
            </div>
            <button
              onClick={testDb}
              disabled={testing}
              className="text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 px-4 py-2 rounded-lg transition-colors disabled:opacity-50"
            >
              {testing ? '⟳ جاري الاختبار...' : '⚡ اختبار الاتصال'}
            </button>
            {testResult && (
              <div className={`text-xs px-3 py-2 rounded-lg ${testResult.ok ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}`}>
                {testResult.ok ? '✓' : '✗'} {testResult.msg}
              </div>
            )}
          </Section>

          {/* Admin */}
          <Section title="حساب المسؤول الأعلى (Super Admin)" icon="👤">
            <div className="grid grid-cols-2 gap-4">
              <Field label="الاسم الكامل" value={admin.name} onChange={v => setAdmin(p => ({ ...p, name: v }))} placeholder="اسمك الكامل" />
              <Field label="البريد الإلكتروني" value={admin.email} onChange={v => setAdmin(p => ({ ...p, email: v }))} placeholder="admin@company.com" type="email" />
            </div>
            <Field label={done ? 'كلمة مرور جديدة (اتركها فارغة للإبقاء)' : 'كلمة المرور *'} value={admin.password}
              onChange={v => setAdmin(p => ({ ...p, password: v }))} placeholder="••••••••" type="password" />
          </Section>

          {/* Platform & Keys */}
          <Section title="المنصة ومفاتيح الذكاء الاصطناعي" icon="🔑">
            <Field label="اسم المنصة" value={platform.name} onChange={v => setPlatform(p => ({ ...p, name: v }))} placeholder="AI Recruit" />
            <Field label="مفتاح OpenAI API *" value={platform.openai_key}
              onChange={v => setPlatform(p => ({ ...p, openai_key: v }))} placeholder="sk-..."
              type="password" hint={done && !platform.openai_key ? '✓ مُضبَّط مسبقاً — اتركه فارغاً للإبقاء' : 'مطلوب لجميع ميزات الذكاء الاصطناعي'} />
            <Field label="مفتاح HeyGen API (اختياري)" value={platform.heygen_key}
              onChange={v => setPlatform(p => ({ ...p, heygen_key: v }))} placeholder="..."
              type="password" hint={done && !platform.heygen_key ? '✓ مُضبَّط مسبقاً — اتركه فارغاً للإبقاء' : 'للمقابلات بالفيديو (Avatar)'} />
          </Section>

          {/* ── Main Action Button ── */}
          {!done ? (
            <button
              onClick={install}
              disabled={installing || locked}
              className="w-full py-4 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700
                disabled:opacity-50 text-white font-bold text-base rounded-2xl shadow-lg shadow-violet-500/30 transition-all flex items-center justify-center gap-3"
            >
              {installing ? (
                <>
                  <span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  جاري التثبيت...
                </>
              ) : '🚀 تثبيت المنصة'}
            </button>
          ) : (
            <button
              onClick={save}
              disabled={saving || locked}
              className="w-full py-4 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700
                disabled:opacity-50 text-white font-bold text-base rounded-2xl shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-3"
            >
              {saving ? (
                <>
                  <span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  جاري الحفظ...
                </>
              ) : '💾 حفظ التغييرات'}
            </button>
          )}

          {/* ══════════════ TERMINAL ══════════════ */}
          <div className="bg-[#0d1117] border border-[#30363d] rounded-2xl overflow-hidden">
            <div className="flex items-center justify-between px-5 py-3 bg-[#161b22] border-b border-[#30363d]">
              <div className="flex items-center gap-2">
                <div className="flex gap-1.5">
                  <span className="w-3 h-3 rounded-full bg-[#ff5f57]" />
                  <span className="w-3 h-3 rounded-full bg-[#febc2e]" />
                  <span className="w-3 h-3 rounded-full bg-[#28c840]" />
                </div>
                <span className="text-[#8b949e] text-xs font-mono mr-2">server terminal</span>
              </div>
              <span className="text-[#8b949e] text-xs">{locked ? '🔒 مقفل' : '● متصل'}</span>
            </div>

            {/* Quick commands */}
            <div className="px-4 pt-3 pb-2 flex flex-wrap gap-1.5 border-b border-[#30363d]">
              {QUICK_CMDS.map(c => (
                <button key={c} onClick={() => { setCmd(c); }} disabled={locked}
                  className="text-xs px-2 py-0.5 rounded bg-[#1c2128] border border-[#30363d] text-[#8b949e] hover:text-[#c9d1d9] hover:border-[#58a6ff] transition-colors disabled:opacity-30 font-mono">
                  {c}
                </button>
              ))}
            </div>

            {/* Output */}
            <div ref={termRef} className="h-52 overflow-y-auto p-4 font-mono text-xs space-y-2 bg-[#0d1117]">
              {termHistory.length === 0 && (
                <p className="text-[#484f58]"># اكتب أمراً أو اختر من الأوامر السريعة أعلاه</p>
              )}
              {termHistory.map((h, i) => (
                <div key={i}>
                  <div className="text-[#58a6ff]">$ {h.cmd}</div>
                  <pre className={`whitespace-pre-wrap text-xs leading-relaxed ${h.ok ? 'text-[#3fb950]' : 'text-[#f85149]'}`}>
                    {h.output}
                  </pre>
                </div>
              ))}
              {running && <div className="text-[#484f58] animate-pulse">جاري التنفيذ...</div>}
            </div>

            {/* Input */}
            <div className="flex items-center gap-2 px-4 py-3 bg-[#161b22] border-t border-[#30363d]">
              <span className="text-[#58a6ff] text-xs font-mono">$</span>
              <input
                type="text"
                value={cmd}
                onChange={e => setCmd(e.target.value)}
                onKeyDown={onTermKey}
                disabled={running || locked}
                placeholder={locked ? 'الترمينال مقفل' : 'php artisan ...'}
                className="flex-1 bg-transparent text-[#c9d1d9] text-xs font-mono focus:outline-none placeholder-[#484f58] disabled:opacity-40"
              />
              <button onClick={runCmd} disabled={running || !cmd.trim() || locked}
                className="text-xs px-3 py-1 bg-[#238636] hover:bg-[#2ea043] disabled:opacity-30 text-white rounded transition-colors">
                تشغيل
              </button>
            </div>
          </div>

          {/* ══════════════ SECURITY ══════════════ */}
          <div className="bg-red-950/30 border border-red-800/40 rounded-2xl p-5">
            <h3 className="font-bold text-red-400 text-sm mb-2">🔐 الأمان — قفل صفحة الإعداد</h3>
            <p className="text-xs text-red-300/70 leading-relaxed mb-4">
              بعد الانتهاء من الإعداد، يُنصح بقفل هذه الصفحة لمنع أي أحد من الوصول إليها.
              سيتم إنشاء ملف <code className="text-red-300">storage/setup.lock</code> على السيرفر.
              لإعادة الفتح لاحقاً، احذف هذا الملف يدوياً.
            </p>
            <button
              onClick={lockSetup}
              disabled={locking || locked}
              className="w-full py-2.5 bg-red-700/40 hover:bg-red-700/60 disabled:opacity-40 border border-red-700/50
                text-red-300 text-sm font-semibold rounded-xl transition-colors"
            >
              {locking ? 'جاري القفل...' : locked ? '✓ الصفحة مقفلة' : '🔒 قفل صفحة الإعداد نهائياً'}
            </button>
          </div>

        </div>
      </div>
    </div>
  );
}

/* ── Stars background ── */
function Stars() {
  return (
    <div className="fixed inset-0 overflow-hidden pointer-events-none">
      {Array.from({ length: 60 }, (_, i) => (
        <div key={i} className="absolute rounded-full bg-white opacity-[0.15]"
          style={{ width: i % 5 === 0 ? 2 : 1, height: i % 5 === 0 ? 2 : 1, top: `${(i * 37 + 11) % 100}%`, left: `${(i * 61 + 7) % 100}%` }} />
      ))}
    </div>
  );
}
