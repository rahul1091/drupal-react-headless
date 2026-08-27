import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { getTasks, getClientList } from "../api/client";
import "../css/tasklist.css";

const COLUMNS = [
  { key: "open", label: "Open" },
  { key: "in_progress", label: "In Progress" },
  { key: "completed", label: "Completed" },
  { key: "cancelled", label: "Cancelled" },
];

export default function TaskList() {
  const [tasks, setTasks] = useState([]);
  const [projects, setProjects] = useState([]);
  const [selectedProject, setSelectedProject] = useState("all");
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();
  const { user } = useAuth();
  const isAdmin = !!user?.isAdmin;

  console.log("projects: ", projects); // Corrected from tasks to projects

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);

    Promise.all([getTasks(), getClientList()])
      .then(([tasksRes, projectsRes]) => {
        if (!isMounted) return;
        setTasks(tasksRes.data?.result || []);

        // Adjust based on how your getClientList API returns its data array
        const projectData = projectsRes.data?.result || projectsRes.data || [];
        setProjects(projectData);
      })
      .catch((err) => {
        if (!isMounted) return;
        console.error("Error fetching initial data:", err);
        setError("Failed to load task list. Please try again.");
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const normaliseStatus = (raw = "") =>
    raw.toLowerCase().replace(/[\s-]+/g, "_");

  // Map project names from the fetched projects list.
  // Falls back to unique project names from tasks if projects endpoint returns objects or strings.
  const uniqueProjects = Array.from(
    new Set(
      projects.length > 0
        ? projects.map((p) =>
            typeof p === "string" ? p : p.name || p.project_name,
          )
        : tasks.map((task) => task.project_name),
    ),
  ).filter(Boolean);

  // Filter tasks based on the selected project
  const filteredTasks = tasks.filter((task) => {
    if (selectedProject === "all") return true;
    return task.project_name === selectedProject;
  });

  const grouped = COLUMNS.reduce((acc, col) => {
    acc[col.key] = [];
    return acc;
  }, {});
  const other = [];

  filteredTasks.forEach((task) => {
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
          <h2>Task List ({filteredTasks.length})</h2>
          <p className="tasklist-scope">
            {isAdmin
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

      {/* Project Filter Controls - Handling empty projects state */}
      {!isLoading && !error && (
        <div className="tasklist-filter-bar">
          <label htmlFor="project-filter">Filter by Project:</label>
          {projects.length === 0 ? (
            <span
              className="no-project-assigned-text"
              style={{ fontWeight: "500", marginLeft: "8px" }}
            >
              No Project Assigned
            </span>
          ) : (
            <select
              id="project-filter"
              value={selectedProject}
              onChange={(e) => setSelectedProject(e.target.value)}
            >
              <option value="all">All Projects</option>
              {uniqueProjects.map((projectName) => (
                <option key={projectName} value={projectName}>
                  {projectName}
                </option>
              ))}
            </select>
          )}
        </div>
      )}

      {isLoading && <div className="tasklist-loading">Loading tasks...</div>}
      {error && <div className="tasklist-error">{error}</div>}

      {!isLoading && !error && (
        <div className="tasklist-body">
          {filteredTasks.length === 0 ? (
            <div className="no-tasks">No Active Tasks</div>
          ) : (
            <div className="task-board">
              {COLUMNS.map((col) => {
                const colTasks =
                  col.key === "open"
                    ? [...(grouped.open || []), ...other]
                    : grouped[col.key] || [];

                return (
                  <div
                    key={col.key}
                    className={`task-column task-column--${col.key}`}
                  >
                    <div className="task-column__header">
                      <span className={`badge status-${col.key}`}>
                        {col.label}
                      </span>
                      <span className="task-column__count">
                        {colTasks.length}
                      </span>
                    </div>

                    <div className="task-column__cards">
                      {colTasks.length === 0 ? (
                        <div className="task-column__empty">No Tasks</div>
                      ) : (
                        colTasks.map((task) => (
                          <div key={task.id} className="task-card">
                            <div className="task-card-project-header">
                              <h2>{task.project_name}</h2>
                            </div>
                            <div className="task-card-header">
                              <h3>{task.title}</h3>
                            </div>
                            <p className="task-description">
                              {task.description}
                            </p>
                            <div className="task-card-people">
                              {task.created_by?.name && (
                                <p className="task-meta">
                                  Created by{" "}
                                  <strong>
                                    {task.created_by.fullname ||
                                      task.created_by.name}
                                  </strong>
                                </p>
                              )}
                              {isAdmin && task.assigned_to?.name && (
                                <p className="task-meta">
                                  Assigned to{" "}
                                  <strong>
                                    {task.assigned_to.fullname ||
                                      task.assigned_to.name}
                                  </strong>
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
                            {!isAdmin && (
                              <button
                                type="button"
                                className="task-edit-btn"
                                onClick={() =>
                                  navigate(`/edit-task/${task.id}`, {
                                    state: { task },
                                  })
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
