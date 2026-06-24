'use client';

import { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import axios from 'axios';

const API = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface Job {
  id: number;
  title: string;
  department?: { name: string };
  location: string;
  employment_type: string;
  salary_min?: number;
  salary_max?: number;
  salary_currency?: string;
  description: string;
  requirements: string;
  published_at: string;
  expires_at?: string;
}

interface CareerPage {
  name: string;
  career_page_title: string;
  career_page_description: string;
  logo?: string;
  primary_color?: string;
}

export default function CareersPage() {
  const { slug } = useParams<{ slug: string }>();
  const [company, setCompany] = useState<CareerPage | null>(null);
  const [jobs, setJobs] = useState<Job[]>([]);
  const [search, setSearch] = useState('');
  const [selectedJob, setSelectedJob] = useState<Job | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const load = async () => {
      try {
        const [companyRes, jobsRes] = await Promise.all([
          axios.get(`${API}/careers/${slug}`),
          axios.get(`${API}/careers/${slug}/jobs`),
        ]);
        setCompany(companyRes.data);
        setJobs(jobsRes.data);
      } catch {
        setError('Career page not found');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [slug]);

  const filtered = jobs.filter(j =>
    j.title.toLowerCase().includes(search.toLowerCase()) ||
    (j.department?.name || '').toLowerCase().includes(search.toLowerCase())
  );

  const primaryColor = company?.primary_color || '#7C3AED';

  if (loading) return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="w-10 h-10 border-4 border-violet-600 border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (error) return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="text-center">
        <div className="text-6xl mb-4">🔍</div>
        <h1 className="text-2xl font-bold text-gray-800">Page Not Found</h1>
        <p className="text-gray-500 mt-2">This career page doesn't exist or has been removed.</p>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Hero Header */}
      <div style={{ background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryColor}cc 100%)` }} className="text-white py-20 px-6">
        <div className="max-w-4xl mx-auto text-center">
          {company?.logo && (
            <img src={company.logo} alt={company.name} className="h-16 mx-auto mb-6 rounded-xl object-contain bg-white/10 p-2" />
          )}
          <h1 className="text-4xl font-bold mb-4">
            {company?.career_page_title || `Careers at ${company?.name}`}
          </h1>
          {company?.career_page_description && (
            <p className="text-lg text-white/80 max-w-2xl mx-auto">{company.career_page_description}</p>
          )}
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-6 py-12">
        {/* Search */}
        <div className="mb-8">
          <input
            type="text"
            placeholder="Search jobs..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full max-w-lg px-4 py-3 rounded-xl border border-gray-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 text-sm"
          />
          <p className="text-sm text-gray-500 mt-2">{filtered.length} open position{filtered.length !== 1 ? 's' : ''}</p>
        </div>

        <div className="grid lg:grid-cols-5 gap-6">
          {/* Job List */}
          <div className="lg:col-span-2 space-y-3">
            {filtered.length === 0 && (
              <div className="text-center py-16 text-gray-400">
                <div className="text-4xl mb-3">📋</div>
                <p>No open positions found</p>
              </div>
            )}
            {filtered.map(job => (
              <button
                key={job.id}
                onClick={() => setSelectedJob(job)}
                className={`w-full text-left p-4 rounded-xl border transition-all ${
                  selectedJob?.id === job.id
                    ? 'border-violet-500 bg-violet-50 shadow-md'
                    : 'border-gray-200 bg-white hover:border-violet-300 hover:shadow-sm'
                }`}
              >
                <h3 className="font-semibold text-gray-800">{job.title}</h3>
                <p className="text-sm text-gray-500 mt-1">
                  {job.department?.name} {job.department?.name && '·'} {job.location}
                </p>
                <div className="flex items-center gap-2 mt-2">
                  <span className="text-xs px-2 py-0.5 bg-gray-100 rounded-full text-gray-600">
                    {job.employment_type?.replace('_', ' ')}
                  </span>
                  {job.salary_min && (
                    <span className="text-xs text-gray-500">
                      {job.salary_currency || 'USD'} {job.salary_min.toLocaleString()} – {job.salary_max?.toLocaleString()}
                    </span>
                  )}
                </div>
              </button>
            ))}
          </div>

          {/* Job Detail */}
          <div className="lg:col-span-3">
            {selectedJob ? (
              <div className="bg-white rounded-2xl border border-gray-200 p-8 sticky top-6">
                <div className="mb-6">
                  <h2 className="text-2xl font-bold text-gray-800">{selectedJob.title}</h2>
                  <div className="flex flex-wrap gap-3 mt-3">
                    {selectedJob.department?.name && (
                      <span className="text-sm px-3 py-1 bg-violet-100 text-violet-700 rounded-full">
                        {selectedJob.department.name}
                      </span>
                    )}
                    <span className="text-sm px-3 py-1 bg-gray-100 text-gray-700 rounded-full">
                      {selectedJob.location}
                    </span>
                    <span className="text-sm px-3 py-1 bg-blue-100 text-blue-700 rounded-full">
                      {selectedJob.employment_type?.replace('_', ' ')}
                    </span>
                  </div>
                  {selectedJob.salary_min && (
                    <p className="text-sm text-gray-500 mt-2">
                      Salary: {selectedJob.salary_currency || 'USD'} {selectedJob.salary_min.toLocaleString()} – {selectedJob.salary_max?.toLocaleString()}
                    </p>
                  )}
                </div>

                <div className="prose prose-sm max-w-none">
                  <h4 className="font-semibold text-gray-700 mb-2">About the role</h4>
                  <p className="text-gray-600 whitespace-pre-line text-sm leading-relaxed">{selectedJob.description}</p>

                  {selectedJob.requirements && (
                    <>
                      <h4 className="font-semibold text-gray-700 mt-6 mb-2">Requirements</h4>
                      <p className="text-gray-600 whitespace-pre-line text-sm leading-relaxed">{selectedJob.requirements}</p>
                    </>
                  )}
                </div>

                <a
                  href={`/candidate/apply?job=${selectedJob.id}`}
                  style={{ background: primaryColor }}
                  className="mt-8 w-full block text-center text-white py-3 rounded-xl font-semibold hover:opacity-90 transition-opacity"
                >
                  Apply Now
                </a>
              </div>
            ) : (
              <div className="bg-white rounded-2xl border border-gray-200 p-16 text-center text-gray-400 sticky top-6">
                <div className="text-5xl mb-4">👈</div>
                <p>Select a job to see details</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
