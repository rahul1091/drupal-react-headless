import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { addTask } from "../api/client";
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
  });

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
      navigate("/dashboard");
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
          <h2>Create New Project Tracker Task</h2>
          <button
            type="button"
            className="btn-secondary"
            onClick={() => navigate("/dashboard")}
            disabled={isSubmitting}
          >
            &larr; Back to Dashboard
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

          {/* Description */}
          <div className="form-group">
            <label htmlFor="description">
              Description <span className="required">*</span>
            </label>
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

          {/* Due Date */}
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

          {/* Severity */}
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

          {/* Status */}
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

          {/* Form Actions */}
          <div className="form-actions">
            <button
              type="button"
              className="btn-secondary"
              onClick={() => navigate("/dashboard")}
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
