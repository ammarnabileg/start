'use client';

import { useState, useEffect } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { avatarsApi } from '@/lib/api';

interface Avatar {
  id: number;
  name: string;
  heygen_avatar_id: string;
  heygen_voice_id?: string;
  preview_image?: string;
  is_active: boolean;
  created_at: string;
}

interface HeyGenAvatar {
  avatar_id: string;
  avatar_name: string;
  preview_image_url?: string;
}

export default function AvatarsPage() {
  const [avatars, setAvatars] = useState<Avatar[]>([]);
  const [heygenAvatars, setHeygenAvatars] = useState<HeyGenAvatar[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [loadingHeygen, setLoadingHeygen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    name: '',
    heygen_avatar_id: '',
    heygen_voice_id: '',
    preview_image: '',
  });

  useEffect(() => {
    load();
  }, []);

  const load = async () => {
    try {
      const res = await avatarsApi.list();
      setAvatars(res.data);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const loadHeygenAvatars = async () => {
    setLoadingHeygen(true);
    try {
      const res = await avatarsApi.heygenList();
      setHeygenAvatars(res.data?.avatars || res.data || []);
    } catch {
      alert('Failed to load HeyGen avatars. Check your HeyGen API key in Settings.');
    } finally {
      setLoadingHeygen(false);
    }
  };

  const selectHeygenAvatar = (ha: HeyGenAvatar) => {
    setForm(f => ({
      ...f,
      heygen_avatar_id: ha.avatar_id,
      name: f.name || ha.avatar_name,
      preview_image: ha.preview_image_url || '',
    }));
  };

  const save = async () => {
    if (!form.name || !form.heygen_avatar_id) {
      alert('Name and HeyGen Avatar ID are required');
      return;
    }
    setSaving(true);
    try {
      const formData = new FormData();
      Object.entries(form).forEach(([k, v]) => { if (v) formData.append(k, v); });
      await avatarsApi.create(formData);
      setShowModal(false);
      setForm({ name: '', heygen_avatar_id: '', heygen_voice_id: '', preview_image: '' });
      setHeygenAvatars([]);
      load();
    } catch {
      alert('Failed to create avatar');
    } finally {
      setSaving(false);
    }
  };

  const deleteAvatar = async (id: number) => {
    if (!confirm('Delete this avatar?')) return;
    try {
      await avatarsApi.delete(id);
      setAvatars(prev => prev.filter(a => a.id !== id));
    } catch {
      alert('Failed to delete');
    }
  };

  return (
    <DashboardLayout>
      <div className="p-6 max-w-6xl mx-auto">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold text-gray-800">AI Avatars</h1>
            <p className="text-sm text-gray-500 mt-1">Manage HeyGen video avatars for AI-powered video interviews</p>
          </div>
          <button
            onClick={() => setShowModal(true)}
            className="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors"
          >
            + Add Avatar
          </button>
        </div>

        {loading ? (
          <div className="text-center py-16"><div className="w-8 h-8 border-4 border-violet-600 border-t-transparent rounded-full animate-spin mx-auto" /></div>
        ) : avatars.length === 0 ? (
          <div className="text-center py-20 bg-white rounded-2xl border border-gray-200">
            <div className="text-6xl mb-4">🤖</div>
            <h3 className="text-xl font-semibold text-gray-700">No avatars yet</h3>
            <p className="text-gray-500 mt-2 mb-6">Connect your HeyGen account to add video interview avatars</p>
            <button
              onClick={() => setShowModal(true)}
              className="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors"
            >
              Add First Avatar
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {avatars.map(avatar => (
              <div key={avatar.id} className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                {avatar.preview_image ? (
                  <img src={avatar.preview_image} alt={avatar.name} className="w-full h-48 object-cover" />
                ) : (
                  <div className="w-full h-48 bg-gradient-to-br from-violet-100 to-purple-200 flex items-center justify-center">
                    <span className="text-6xl">🎭</span>
                  </div>
                )}
                <div className="p-4">
                  <div className="flex items-start justify-between">
                    <div>
                      <h3 className="font-semibold text-gray-800">{avatar.name}</h3>
                      <p className="text-xs text-gray-400 mt-1 font-mono">{avatar.heygen_avatar_id}</p>
                      {avatar.heygen_voice_id && (
                        <p className="text-xs text-gray-400 font-mono">Voice: {avatar.heygen_voice_id}</p>
                      )}
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${avatar.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                      {avatar.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>
                  <div className="flex gap-2 mt-4">
                    <button
                      onClick={() => deleteAvatar(avatar.id)}
                      className="flex-1 text-xs text-red-600 border border-red-200 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Create Avatar Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-gray-100 flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-800">Add New Avatar</h2>
              <button onClick={() => { setShowModal(false); setHeygenAvatars([]); }} className="text-gray-400 hover:text-gray-600 text-xl">×</button>
            </div>
            <div className="p-6 space-y-5">
              {/* Manual fields */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Avatar Name *</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="e.g. Sarah - HR Manager"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">HeyGen Avatar ID *</label>
                <input
                  type="text"
                  value={form.heygen_avatar_id}
                  onChange={e => setForm(f => ({ ...f, heygen_avatar_id: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="e.g. Abigail_expressive_2024112501"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">HeyGen Voice ID</label>
                <input
                  type="text"
                  value={form.heygen_voice_id}
                  onChange={e => setForm(f => ({ ...f, heygen_voice_id: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="Optional voice ID"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Preview Image URL</label>
                <input
                  type="text"
                  value={form.preview_image}
                  onChange={e => setForm(f => ({ ...f, preview_image: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="https://..."
                />
              </div>

              {/* Browse from HeyGen */}
              <div className="border-t border-gray-100 pt-4">
                <div className="flex items-center justify-between mb-3">
                  <p className="text-sm font-medium text-gray-700">Or browse from HeyGen library</p>
                  <button
                    onClick={loadHeygenAvatars}
                    disabled={loadingHeygen}
                    className="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                  >
                    {loadingHeygen ? 'Loading...' : 'Load HeyGen Avatars'}
                  </button>
                </div>
                {heygenAvatars.length > 0 && (
                  <div className="grid grid-cols-3 gap-3 max-h-60 overflow-y-auto p-1">
                    {heygenAvatars.map(ha => (
                      <button
                        key={ha.avatar_id}
                        onClick={() => selectHeygenAvatar(ha)}
                        className={`rounded-xl overflow-hidden border-2 transition-all ${
                          form.heygen_avatar_id === ha.avatar_id
                            ? 'border-violet-500 ring-2 ring-violet-200'
                            : 'border-gray-200 hover:border-violet-300'
                        }`}
                      >
                        {ha.preview_image_url ? (
                          <img src={ha.preview_image_url} alt={ha.avatar_name} className="w-full h-24 object-cover" />
                        ) : (
                          <div className="w-full h-24 bg-gray-100 flex items-center justify-center text-2xl">🎭</div>
                        )}
                        <p className="text-xs text-center py-1 px-1 text-gray-700 truncate">{ha.avatar_name}</p>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  onClick={() => { setShowModal(false); setHeygenAvatars([]); }}
                  className="flex-1 py-2 text-sm border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={save}
                  disabled={saving}
                  className="flex-1 py-2 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-50 transition-colors"
                >
                  {saving ? 'Saving...' : 'Save Avatar'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
