import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { getTasks } from "../api/client";
import "../css/tasklist.css";

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
      {error && <div className="tasklist-error">{error}</div>}

      {/* Task Grid Rendering */}
      {!isLoading && !error && (
        <div className="tasklist-body">
          {tasks.length === 0 ? (
            <div className="no-tasks">No tasks found.</div>
          ) : (
            <div className="task-grid">
              {tasks.map((task) => (
                <div key={task.id} className="task-card">
                  <div className="task-card-header">
                    <h3>{task.title}</h3>
                    <span
                      className={`badge status-${(task.status || "")
                        .toLowerCase()
                        .replace(/\s+/g, "-")}`}
                    >
                      {task.status}
                    </span>
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
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
