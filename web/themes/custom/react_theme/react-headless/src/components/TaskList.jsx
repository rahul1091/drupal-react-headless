import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { getTasks } from "../api/client";
import "../css/tasklist.css";

const COLUMNS = [
  { key: "open",        label: "Open" },
  { key: "in_progress", label: "In Progress" },
  { key: "completed",   label: "Completed" },
  { key: "cancelled",   label: "Cancelled" },
];

export default function TaskList() {
  const [tasks, setTasks] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    setIsLoading(true);
    getTasks()
      .then((response) => {
        const fetchedTasks = response.data?.result || [];
        setTasks(fetchedTasks);
      })
      .catch((err) => {
        console.error("Error fetching tasks:", err);
        setError("Failed to load tasks. Please try again.");
      })
      .finally(() => setIsLoading(false));
  }, []);

  // Normalise a task's status to one of the four column keys, so a value
  // that doesn't exactly match (e.g. "In Progress" vs "in_progress") still
  // lands in the right column instead of disappearing.
  const normaliseStatus = (raw = "") =>
    raw.toLowerCase().replace(/[\s-]+/g, "_");

  // Group tasks into column buckets. Tasks whose status doesn't match any
  // column key end up in a catch-all "other" bucket that renders alongside
  // Open (edge case — shouldn't happen with the current Drupal field values).
  const grouped = COLUMNS.reduce((acc, col) => {
    acc[col.key] = [];
    return acc;
  }, {});
  const other = [];

  tasks.forEach((task) => {
    const norm = normaliseStatus(task.status);
    if (grouped[norm] !== undefined) {
      grouped[norm].push(task);
    } else {
      other.push(task);
    }
  });

  return (
    <div className="tasklist-container">
      {/* Header */}
      <div className="tasklist-header">
        <h2>Task List ({tasks.length})</h2>
        <button
          className="add-task-btn"
          onClick={() => navigate("/create-task")}
        >
          + Add Task
        </button>
      </div>

      {/* Loading & Error States */}
      {isLoading && <div className="tasklist-loading">Loading tasks...</div>}
      {error   && <div className="tasklist-error">{error}</div>}

      {/* Kanban Board */}
      {!isLoading && !error && (
        <div className="tasklist-body">
          {tasks.length === 0 ? (
            <div className="no-tasks">No tasks found.</div>
          ) : (
            <div className="task-board">
              {COLUMNS.map((col) => {
                const colTasks = col.key === "open"
                  ? [...(grouped.open || []), ...other]
                  : grouped[col.key] || [];

                return (
                  <div key={col.key} className={`task-column task-column--${col.key}`}>
                    {/* Column header */}
                    <div className="task-column__header">
                      <span className={`badge status-${col.key}`}>{col.label}</span>
                      <span className="task-column__count">{colTasks.length}</span>
                    </div>

                    {/* Cards stack */}
                    <div className="task-column__cards">
                      {colTasks.length === 0 ? (
                        <div className="task-column__empty">No tasks</div>
                      ) : (
                        colTasks.map((task) => (
                          <div key={task.id} className="task-card">
                            <div className="task-card-header">
                              <h3>{task.title}</h3>
                            </div>
                            <p className="task-description">{task.description}</p>
                            {task.created_by?.name && (
                              <p className="task-meta">
                                Created by <strong>{task.created_by.name}</strong>
                              </p>
                            )}
                            <div className="task-card-footer">
                              <span className="task-due-date">
                                📅 Due: {task.due_date}
                              </span>
                              <span className="task-severity">
                                Severity:{" "}
                                <span
                                  className={`badge severity-${(
                                    task.severity || ""
                                  ).toLowerCase()}`}
                                >
                                  {task.severity}
                                </span>
                              </span>
                            </div>
                            <button
                              type="button"
                              className="task-edit-btn"
                              onClick={() =>
                                navigate(`/edit-task/${task.id}`, { state: { task } })
                              }
                            >
                              Edit Task
                            </button>
                          </div>
                        ))
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
