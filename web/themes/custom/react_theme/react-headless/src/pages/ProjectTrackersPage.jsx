/**
 * ProjectTrackersPage.jsx
 *
 * Public listing page for project_tracker content.
 *   - Anyone can view the list.
 *   - Only authenticated users see the "New tracker" button and card actions.
 *   - Supports filtering by status and severity.
 */
import React, { useEffect, useState, useCallback } from "react";
import { useAuth } from "@/hooks/useAuth";
import { useProjectTrackers } from "@/hooks/useProjectTrackers";
import {
  createProjectTracker,
  updateProjectTracker,
  STATUS_OPTIONS,
  SEVERITY_OPTIONS,
} from "@/api/projectTrackers";
import ProjectTrackerCard from "@/components/ProjectTrackerCard";
import ProjectTrackerForm from "@/components/ProjectTrackerForm";
import Modal from "@/components/Modal";
import ConfirmDialog from "@/components/ConfirmDialog";
import Pagination from "@/components/Pagination";

export default function ProjectTrackersPage() {
  const { user } = useAuth();
  const { trackers, meta, loading, error, fetchTrackers, removeTracker } =
    useProjectTrackers(10);

  // Filter state
  const [filterStatus, setFilterStatus] = useState("");
  const [filterSeverity, setFilterSeverity] = useState("");
  const [currentPage, setCurrentPage] = useState(0);

  // Modal state
  const [createOpen, setCreateOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  // Submission state
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [successMsg, setSuccessMsg] = useState("");

  const filters = {
    status: filterStatus || undefined,
    severity: filterSeverity || undefined,
  };

  useEffect(() => {
    fetchTrackers(currentPage, filters);
  }, [currentPage, filterStatus, filterSeverity]);

  const flash = (msg) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(""), 3500);
  };

  const handleFilterChange = (setter) => (e) => {
    setter(e.target.value);
    setCurrentPage(0);
  };

  // --- CREATE ---
  const handleCreate = useCallback(
    async (payload) => {
      setSubmitting(true);
      setSubmitError(null);
      try {
        await createProjectTracker(payload);
        setCreateOpen(false);
        await fetchTrackers(currentPage, filters);
        flash("Project tracker created.");
      } catch (err) {
        setSubmitError(err.message);
      } finally {
        setSubmitting(false);
      }
    },
    [currentPage, filters, fetchTrackers],
  );

  // --- EDIT ---
  const handleEdit = useCallback(
    async (payload) => {
      setSubmitting(true);
      setSubmitError(null);
      try {
        await updateProjectTracker(editTarget.id, payload);
        setEditTarget(null);
        await fetchTrackers(currentPage, filters);
        flash("Project tracker updated.");
      } catch (err) {
        setSubmitError(err.message);
      } finally {
        setSubmitting(false);
      }
    },
    [editTarget, currentPage, filters, fetchTrackers],
  );

  // --- DELETE ---
  const handleDelete = useCallback(async () => {
    setSubmitting(true);
    setSubmitError(null);
    try {
      await removeTracker(deleteTarget.id);
      setDeleteTarget(null);
      flash("Project tracker deleted.");
    } catch (err) {
      setSubmitError(err.message);
    } finally {
      setSubmitting(false);
    }
  }, [deleteTarget, removeTracker]);

  return (
    <div>
      {/* Page header */}
      <div className="page-header">
        <h1>Project Trackers</h1>
        {user && (
          <button
            className="btn btn-primary"
            onClick={() => {
              setSubmitError(null);
              setCreateOpen(true);
            }}
          >
            + New tracker
          </button>
        )}
      </div>

      {/* Filter bar */}
      <div className="filter-bar">
        <select
          className="form-select filter-select"
          value={filterStatus}
          onChange={handleFilterChange(setFilterStatus)}
          aria-label="Filter by status"
        >
          <option value="">All statuses</option>
          {STATUS_OPTIONS.map(({ value, label }) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>

        <select
          className="form-select filter-select"
          value={filterSeverity}
          onChange={handleFilterChange(setFilterSeverity)}
          aria-label="Filter by severity"
        >
          <option value="">All severities</option>
          {SEVERITY_OPTIONS.map(({ value, label }) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>

        {(filterStatus || filterSeverity) && (
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => {
              setFilterStatus("");
              setFilterSeverity("");
              setCurrentPage(0);
            }}
          >
            Clear filters
          </button>
        )}
      </div>

      {/* Alerts */}
      {successMsg && <div className="alert alert-success">{successMsg}</div>}
      {submitError && <div className="alert alert-error">{submitError}</div>}
      {error && <div className="alert alert-error">{error}</div>}

      {/* List */}
      {loading ? (
        <div className="loading-center">
          <span className="spinner spinner-lg" />
        </div>
      ) : trackers.length === 0 ? (
        <div className="empty-state">
          <div className="empty-state__icon">📋</div>
          <h2>No project trackers found</h2>
          <p>
            {filterStatus || filterSeverity
              ? "Try clearing the filters."
              : user
                ? 'Click "+ New tracker" to create the first one.'
                : "No trackers have been created yet."}
          </p>
        </div>
      ) : (
        <div className="tracker-list">
          {trackers.map((tracker) => (
            <ProjectTrackerCard
              key={tracker.id}
              tracker={tracker}
              onEdit={(t) => {
                setSubmitError(null);
                setEditTarget(t);
              }}
              onDelete={(t) => {
                setSubmitError(null);
                setDeleteTarget(t);
              }}
            />
          ))}
        </div>
      )}

      <Pagination meta={meta} onPageChange={(p) => setCurrentPage(p)} />

      {/* Create modal */}
      {createOpen && (
        <Modal title="New project tracker" onClose={() => setCreateOpen(false)}>
          {submitError && (
            <div className="alert alert-error">{submitError}</div>
          )}
          <ProjectTrackerForm
            onSubmit={handleCreate}
            onCancel={() => setCreateOpen(false)}
            isLoading={submitting}
          />
        </Modal>
      )}

      {/* Edit modal */}
      {editTarget && (
        <Modal
          title={`Edit: ${editTarget.title}`}
          onClose={() => setEditTarget(null)}
        >
          {submitError && (
            <div className="alert alert-error">{submitError}</div>
          )}
          <ProjectTrackerForm
            initialValues={editTarget}
            onSubmit={handleEdit}
            onCancel={() => setEditTarget(null)}
            isLoading={submitting}
          />
        </Modal>
      )}

      {/* Delete confirmation */}
      {deleteTarget && (
        <ConfirmDialog
          title="Delete tracker?"
          message={`"${deleteTarget.title}" will be permanently deleted. This cannot be undone.`}
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
          isLoading={submitting}
        />
      )}
    </div>
  );
}
