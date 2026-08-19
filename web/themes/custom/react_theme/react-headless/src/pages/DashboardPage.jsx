import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { getTopics } from "../api/client";
import "../css/dashboard.css";

export default function DashboardPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [topics, setTopics] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedTopic, setSelectedTopic] = useState(null);

  const displayName =
    [user?.firstname, user?.lastname].filter(Boolean).join(" ") || "User";

  useEffect(() => {
    getTopics()
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
  }, []);

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
          <h1>Welcome back {displayName}! 👋</h1>
          <p>Manage your account and explore active topics.</p>
        </div>
      </div>

      <div className="dashboard-content-grid">
        <div className="userinfo-topics-wrapper">
          {/* User Information Panel */}
          <div className="userinfo-card">
            <div className="card-header">
              <h2>User Account Information</h2>
            </div>
            <div className="user-info-body">
              <div className="info-row">
                <span className="info-label">User ID:</span>
                <span className="info-value">{user?.id || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">Username:</span>
                <span className="info-value">{user?.name || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">Email:</span>
                <span className="info-value">{user?.email || "N/A"}</span>
              </div>
              <div className="info-row">
                <span className="info-label">User Role:</span>
                <span className="info-value">{user?.role || "N/A"}</span>
              </div>
							<div className="info-row">
                <span className="info-label">Account Created:</span>
                <span className="info-value">{user?.created || "N/A"}</span>
              </div>
            </div>
          </div>

          {/* Trending Topics Section */}
          <div className="topics-card">
            <div className="card-header">
              <h2>Trending Topics ({topics.length})</h2>
              {user?.isAdmin && (
                <button
                  type="button"
                  className="add-topic-btn"
                  onClick={() => navigate("/add-topic")}
                >
                  + Add Topic
                </button>
              )}
            </div>

            {error ? (
              <div className="alert alert-error">{error}</div>
            ) : topics.length === 0 ? (
              <div className="no-topics">No trending topics found.</div>
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
                      Read More...
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      <div className="dashboard-tasks-nav">
        <div className="tasks-nav-info">
          <h2>Project Tracker</h2>
          <p>
            {user?.isSuperAdmin
              ? "View and manage all tasks across all users."
              : "View and manage tasks assigned to you."}
          </p>
        </div>
        <button
          type="button"
          className="add-topic-btn"
          onClick={() => navigate("/tasks")}
        >
          View Task List →
        </button>
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
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
