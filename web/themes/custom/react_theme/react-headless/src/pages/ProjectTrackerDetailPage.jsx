import React, { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { getProjectTracker } from '@/api/projectTrackers';

const STATUS_LABELS   = { open: 'Open', in_progress: 'In Progress', on_hold: 'On Hold', completed: 'Completed', cancelled: 'Cancelled' };
const SEVERITY_LABELS = { low: 'Low', medium: 'Medium', high: 'High', critical: 'Critical' };

export default function ProjectTrackerDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [tracker, setTracker] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);

  useEffect(() => {
    getProjectTracker(id)
      .then(setTracker)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="loading-center"><span className="spinner spinner-lg" /></div>;
  if (error)   return <div className="alert alert-error">{error}</div>;
  if (!tracker) return null;

  const created = tracker.createdAt
    ? new Date(tracker.createdAt).toLocaleDateString('en-US', { dateStyle: 'long' })
    : '';
  const due = tracker.due_date
    ? new Date(tracker.due_date + 'T00:00:00').toLocaleDateString('en-US', { dateStyle: 'long' })
    : null;
  const isOverdue = tracker.due_date && new Date(tracker.due_date) < new Date()
    && !['completed', 'cancelled'].includes(tracker.status);

  return (
    <div>
      <div style={{ marginBottom: '1.25rem' }}>
        <Link to="/project-trackers" className="btn btn-ghost btn-sm">← Back to trackers</Link>
      </div>

      <article className="card">
        {/* Badges */}
        <div style={{ display: 'flex', gap: '.6rem', flexWrap: 'wrap', marginBottom: '.75rem' }}>
          <span className={`badge badge-status badge-status--${tracker.status}`}>
            {STATUS_LABELS[tracker.status] || tracker.status}
          </span>
          <span className={`badge badge-severity badge-severity--${tracker.severity}`}>
            {SEVERITY_LABELS[tracker.severity] || tracker.severity}
          </span>
          <span style={{ fontSize: '.8rem', color: 'var(--c-muted)', alignSelf: 'center' }}>
            #{tracker.id}
          </span>
        </div>

        <h1 style={{ fontSize: '1.6rem', fontWeight: 700, marginBottom: '.75rem' }}>
          {tracker.title}
        </h1>

        {/* Meta table */}
        <dl className="tracker-detail__meta">
          <div>
            <dt>Author</dt>
            <dd>{tracker.author}</dd>
          </div>
          <div>
            <dt>Created</dt>
            <dd>{created}</dd>
          </div>
          {due && (
            <div>
              <dt>Due</dt>
              <dd style={{ color: isOverdue ? 'var(--c-danger)' : 'inherit' }}>
                {isOverdue ? '⚠ ' : ''}{due}
              </dd>
            </div>
          )}
        </dl>

        {/* Description */}
        {tracker.description ? (
          <div
            className="article-detail__body"
            style={{ marginTop: '1.5rem' }}
            dangerouslySetInnerHTML={{ __html: tracker.description }}
          />
        ) : (
          <p style={{ color: 'var(--c-muted)', marginTop: '1rem' }}>No description provided.</p>
        )}

        <div style={{ marginTop: '1.75rem', paddingTop: '1rem', borderTop: '1px solid var(--c-border)' }}>
          <button className="btn btn-ghost btn-sm" onClick={() => navigate('/project-trackers')}>
            ← All trackers
          </button>
        </div>
      </article>
    </div>
  );
}
