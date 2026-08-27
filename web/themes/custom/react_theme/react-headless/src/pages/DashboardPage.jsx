import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { getTopics } from "../api/client";
import "../css/dashboard.css";
import { useTranslation } from "react-i18next";

export default function DashboardPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [topics, setTopics] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedTopic, setSelectedTopic] = useState(null);
	const { t, i18n } = useTranslation();

  const displayName =
    [user?.firstname, user?.lastname].filter(Boolean).join(" ") || "User";

  useEffect(() => {
    getTopics(i18n.language)
      .then((response) => {
        const rawData = response.data.result || [];

        // Filter strictly for topics marked as trending
        const trendingOnly = rawData.filter((topic) => {
          const isTrending = String(
            topic.trending || topic.field_trending,
          ).toLowerCase();
          return (
            isTrending === "yes" ||
            isTrending === "true" ||
            topic.trending === true
          );
        });

        setTopics(trendingOnly);
      })
      .catch((err) => {
        console.error("Error fetching topics:", err);
        setError("Failed to load trending topics.");
      })
      .finally(() => setLoading(false));
  }, [i18n.language]);

  if (loading) {
    return (
      <div className="skeleton-grid">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="skeleton-card">
            <div className="skeleton-line" />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="dashboard-container">
      {/* Welcome Banner */}
      <div className="dashboard-header">
        <div>
          <h1>{t("app.welcomeBack")} {displayName}! 👋</h1>
          <p>{t("dashboard.description")}</p>
        </div>
      </div>

      <div className="dashboard-content-grid">
        <div className="userinfo-topics-wrapper">
          {/* User Information Panel */}
          <div className="userinfo-card">
            <div className="card-header">
              <h2>{t("user.userInformation")}</h2>
            </div>
            <div className="user-info-body">
              <div className="info-row">
                <span className="info-label">{t("user.userId")}:</span>
                <span className="info-value">{user?.id || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">{t("user.username")}:</span>
                <span className="info-value">{user?.name || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">{t("user.email")}:</span>
                <span className="info-value">{user?.email || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">{t("user.role")}:</span>
                <span className="info-value">{user?.role || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">{t("user.accountCreated")}:</span>
                <span className="info-value">{user?.created || "N/A"}</span>
              </div>
            </div>
          </div>

          {/* Trending Topics Section */}
          <div className="topics-card">
            <div className="card-header">
              <h2>{t("dashboard.trendingTopics")} ({topics.length})</h2>
              {user?.isAdmin && (
                <button
                  type="button"
                  className="add-topic-btn"
                  onClick={() => navigate("/add-topic")}
                >
                  + {t("topic.addTopic")}
                </button>
              )}
            </div>

            {error ? (
              <div className="alert alert-error">{error}</div>
            ) : topics.length === 0 ? (
              <div className="no-topics">{t("dashboard.noTopicsFound")}</div>
            ) : (
              <div className="dashboard-topic-grid">
                {topics.map((topic, index) => (
                  <div
                    key={topic.id || topic.tid || index}
                    className="dashboard-topic-card"
                  >
                    <div className="topic-info">
                      <h3>{topic.title}</h3>
                      <h4>{topic.subheading}</h4>
                    </div>
                    <button onClick={() => setSelectedTopic(topic)}>
                      {t("dashboard.readMore")}
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

			<h2 className="dashboard-project-section-title">View & Manage Project Information</h2>
			<div className="project-task-wrapper">
				{user?.isAdmin && (
					<div className="dashboard-projects-info">
						<div className="projects-nav-info">
							<h2>Project Details</h2>
							<p>View and manage all projects across the organization</p>
						</div>
						<button
							type="button"
							className="add-project-btn"
							onClick={() => navigate("/projects")}
						>
							View Project Details
						</button>
					</div>
				)}

				<div className="dashboard-tasks-nav">
					<div className="tasks-nav-info">
						<h2>{t("dashboard.projectTracker")}</h2>
						<p>
							{user?.isAdmin
								? t("dashboard.projectTrackerDescription")
								: t("dashboard.description")}
						</p>
					</div>
					<button
						type="button"
						className="add-topic-btn"
						onClick={() => navigate("/tasks")}
					>
						{t("dashboard.viewTaskList")}
					</button>
				</div>
			</div>

      {/* Detail Modal */}
      {selectedTopic && (
        <div className="modal-overlay" onClick={() => setSelectedTopic(null)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button
              onClick={() => setSelectedTopic(null)}
              className="modal-close-btn"
              aria-label="Close modal"
            >
              &times;
            </button>
            <h2 className="modal-title">
              {selectedTopic.title || selectedTopic.name}
            </h2>
            {selectedTopic.subheading && (
              <h3 className="modal-subheading">{selectedTopic.subheading}</h3>
            )}
            <hr className="modal-divider" />
            <div
              className="modal-body"
              dangerouslySetInnerHTML={{
                __html:
                  selectedTopic.description || "No description available.",
              }}
            />
            <div className="modal-footer">
              <button
                onClick={() => setSelectedTopic(null)}
                className="modal-action-btn"
              >
                {t("common.close")}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
