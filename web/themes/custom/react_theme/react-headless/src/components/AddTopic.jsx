import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { addTopic } from "../api/client";
import "../css/tasklist.css";
import { useTranslation } from "react-i18next";

export default function AddTopic() {
	const { t } = useTranslation();
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
            <h2>{t("topic.addNewTopic")}</h2>
            <p className="create-task-subtitle">
              <span className="required">*</span> {t("common.requiredFields")}
            </p>
          </div>
          <button
            type="button"
            className="btn-secondary btn-back"
            onClick={() => navigate("/dashboard")}
            disabled={isSubmitting}
          >
            <span aria-hidden="true">&larr;</span> {t("common.backToDashboard")}
          </button>
        </div>

        <form onSubmit={handleSubmit} className="task-form">
          {/* Title */}
          <div className="form-group">
            <label htmlFor="title">
              {t("common.title")} <span className="required">*</span>
            </label>
            <input
              type="text"
              id="title"
              name="title"
              value={formData.title}
              onChange={handleInputChange}
              required
              placeholder={t("topic.enterTitle")}
              disabled={isSubmitting}
            />
          </div>

          {/* Sub Heading */}
          <div className="form-group">
            <label htmlFor="subheading">{t("topic.subHeading")}</label>
            <input
              type="text"
              id="subheading"
              name="subheading"
              value={formData.subheading}
              onChange={handleInputChange}
              placeholder={t("topic.enterSubHeading")}
              disabled={isSubmitting}
            />
          </div>

          {/* Description */}
          <div className="form-group">
            <div className="form-label-row">
              <label htmlFor="description">
                {t("topic.description")} <span className="required">*</span>
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
              placeholder={t("topic.enterDescription")}
              disabled={isSubmitting}
            />
          </div>

          {/* Trending */}
          <div className="form-group">
            <label htmlFor="trending">
              {t("topic.trending")} <span className="required">*</span>
            </label>
            <select
              id="trending"
              name="trending"
              value={formData.trending}
              onChange={handleInputChange}
              required
              disabled={isSubmitting}
            >
              <option value="yes">{t("topic.yes")}</option>
              <option value="no">{t("topic.no")}</option>
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
              {t("common.cancel")}
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? t("common.saving") : t("topic.saveTopic")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
