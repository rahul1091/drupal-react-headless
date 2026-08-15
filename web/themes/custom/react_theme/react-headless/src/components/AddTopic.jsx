import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { addTopic } from "../api/client";
import "../css/tasklist.css";

export default function AddTopic() {
  const navigate = useNavigate();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    title: "",
    subheading: "",
    description: "",
    trending: "no",
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
      await addTopic(formData);
      navigate("/dashboard");
    } catch (err) {
      console.error("Failed to create topic:", err);
      alert(err.message || "Failed to save topic. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="create-task-container">
      <div className="create-task-card">
        <div className="create-task-header">
          <div>
            <h2>Add New Topic</h2>
            <p className="create-task-subtitle">
              Fields marked <span className="required">*</span> are required.
              Visible to everyone once saved.
            </p>
          </div>
          <button
            type="button"
            className="btn-secondary btn-back"
            onClick={() => navigate("/dashboard")}
            disabled={isSubmitting}
          >
            <span aria-hidden="true">&larr;</span> Back to Dashboard
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
              placeholder="Enter topic title"
              disabled={isSubmitting}
            />
          </div>

          {/* Sub Heading */}
          <div className="form-group">
            <label htmlFor="subheading">Sub Heading</label>
            <input
              type="text"
              id="subheading"
              name="subheading"
              value={formData.subheading}
              onChange={handleInputChange}
              placeholder="Enter a short sub heading"
              disabled={isSubmitting}
            />
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
              placeholder="Enter topic description"
              disabled={isSubmitting}
            />
          </div>

          {/* Trending */}
          <div className="form-group">
            <label htmlFor="trending">
              Trending <span className="required">*</span>
            </label>
            <select
              id="trending"
              name="trending"
              value={formData.trending}
              onChange={handleInputChange}
              required
              disabled={isSubmitting}
            >
              <option value="yes">Yes</option>
              <option value="no">No</option>
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
              {isSubmitting ? "Saving..." : "Save Topic"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
