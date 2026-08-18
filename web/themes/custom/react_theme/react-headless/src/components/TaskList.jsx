import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
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
  const { user } = useAuth();
  const isSuperAdmin = !!user?.isSuperAdmin;

  useEffect(() => {
    setIsLoading(true);
    getTasks()
      .then((response) => {
        setTasks(response.data?.result || []);
      })
      .catch((err) => {
        console.error("Error fetching tasks:", err);
        setError("Failed to load tasks. Please try again.");
      })
      .finally(() => setIsLoading(false));
  }, []);

  const normaliseStatus = (raw = "") =>
    raw.toLowerCase().replace(/[\s-]+/g, "_");

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
      <div className="tasklist-header">
        <div>
          <div className="tasklist-breadcrumb">
            <button
              type="button"
              className="back-to-dashboard-link"
              onClick={() => navigate("/dashboard")}
            >
              ← Dashboard
            </button>
            <span className="breadcrumb-sep">/</span>
            <span>Task List</span>
          </div>
          <h2>Task List ({tasks.length})</h2>
          <p className="tasklist-scope">
            {isSuperAdmin
              ? "Showing all tasks across all users."
              : "Showing tasks assigned to you."}
          </p>
        </div>
        <button
          className="add-task-btn"
          onClick={() => navigate("/create-task")}
        >
          + Add Task
        </button>
      </div>

      {isLoading && <div className="tasklist-loading">Loading tasks...</div>}
      {error   && <div className="tasklist-error">{error}</div>}

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
                    <div className="task-column__header">
                      <span className={`badge status-${col.key}`}>{col.label}</span>
                      <span className="task-column__count">{colTasks.length}</span>
                    </div>

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
                            <div className="task-card-people">
                              {task.created_by?.name && (
                                <p className="task-meta">
                                  Created by <strong>{task.created_by.fullname || task.created_by.name}</strong>
                                </p>
                              )}
                              {isSuperAdmin && task.assigned_to?.name && (
                                <p className="task-meta">
                                  Assigned to <strong>{task.assigned_to.fullname || task.assigned_to.name}</strong>
                                </p>
                              )}
                            </div>
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
                            {/* Superadmin views all tasks for oversight only;
                                editing is restricted to the assignee's own route. */}
                            {!isSuperAdmin && (
                              <button
                                type="button"
                                className="task-edit-btn"
                                onClick={() =>
                                  navigate(`/edit-task/${task.id}`, { state: { task } })
                                }
                              >
                                Edit Task
                              </button>
                            )}
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
