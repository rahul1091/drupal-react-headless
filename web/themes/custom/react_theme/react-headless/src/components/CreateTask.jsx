import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { addTask, getUsers } from "../api/client";
import "../css/tasklist.css";

export default function CreateTask() {
  const navigate = useNavigate();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    title: "",
    description: "",
    due_date: "",
    severity: "low",
    status: "open",
    assigned_to: "",
  });

  const [users, setUsers] = useState([]);
  const [usersLoading, setUsersLoading] = useState(true);
  const [usersError, setUsersError] = useState(null);

  useEffect(() => {
    getUsers()
      .then((response) => {
        setUsers(response.data?.result || []);
      })
      .catch((err) => {
        console.error("Failed to load users:", err);
        setUsersError("Couldn't load the list of users to assign this task to.");
      })
      .finally(() => setUsersLoading(false));
  }, []);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      await addTask(formData);
      navigate("/tasks");
    } catch (err) {
      console.error("Failed to create task:", err);
      alert("Failed to save task. Please check network or authentication.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="create-task-container">
      <div className="create-task-card">
        <div className="create-task-header">
          <div>
            <h2>Create New Project Tracker Task</h2>
            <p className="create-task-subtitle">
              Fields marked <span className="required">*</span> are required.
            </p>
          </div>
          <button
            type="button"
            className="btn-secondary btn-back"
            onClick={() => navigate("/tasks")}
            disabled={isSubmitting}
          >
            <span aria-hidden="true">&larr;</span> Back to Tasks
          </button>
        </div>

        <form onSubmit={handleSubmit} className="task-form">
          {/* Title */}
          <div className="form-group">
            <label htmlFor="title">
              Title <span className="required">*</span>
            </label>
            <input
              type="text"
              id="title"
              name="title"
              value={formData.title}
              onChange={handleInputChange}
              required
              placeholder="Enter task title"
              disabled={isSubmitting}
            />
          </div>

          {/* Assign To */}
          <div className="form-group">
            <label htmlFor="assigned_to">
              Assign To <span className="required">*</span>
            </label>
            <select
              id="assigned_to"
              name="assigned_to"
              value={formData.assigned_to}
              onChange={handleInputChange}
              required
              disabled={isSubmitting || usersLoading || !!usersError}
            >
              <option value="" disabled>
                {usersLoading ? "Loading users..." : "Select a user"}
              </option>
              {users.map((u) => (
                <option key={u.uid} value={u.uid}>
                  {u.fullname || u.name}
                </option>
              ))}
            </select>
            {usersError && <p className="field-error">{usersError}</p>}
          </div>

          {/* Description */}
          <div className="form-group">
            <div className="form-label-row">
              <label htmlFor="description">
                Description <span className="required">*</span>
              </label>
              <span className="char-count">
                {formData.description.length} characters
              </span>
            </div>
            <textarea
              id="description"
              name="description"
              value={formData.description}
              onChange={handleInputChange}
              rows="4"
              required
              placeholder="Enter detailed description"
              disabled={isSubmitting}
            />
          </div>

          {/* Due Date / Severity / Status - grouped together since they're
              all short, single-value fields that together describe the
              task's schedule and priority. */}
          <div className="form-row form-row--3col">
            <div className="form-group">
              <label htmlFor="due_date">
                Due Date <span className="required">*</span>
              </label>
              <input
                type="date"
                id="due_date"
                name="due_date"
                value={formData.due_date}
                onChange={handleInputChange}
                required
                disabled={isSubmitting}
              />
            </div>

            <div className="form-group">
              <label htmlFor="severity">
                Severity <span className="required">*</span>
              </label>
              <select
                id="severity"
                name="severity"
                value={formData.severity}
                onChange={handleInputChange}
                required
                disabled={isSubmitting}
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="status">
                Status <span className="required">*</span>
              </label>
              <select
                id="status"
                name="status"
                value={formData.status}
                onChange={handleInputChange}
                required
                disabled={isSubmitting}
              >
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          {/* Form Actions */}
          <div className="form-actions">
            <button
              type="button"
              className="btn-secondary"
              onClick={() => navigate("/tasks")}
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? "Saving..." : "Save Task"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
