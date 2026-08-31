import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { userDashboard } from "../api/client";
import "../css/userdashboard.css";

export default function DashboardInfo() {
  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchDashboard = async () => {
      try {
        const response = await userDashboard();
        const data = response.data?.result || response.result;
        setDashboardData(data);
      } catch (err) {
        setError(err.message || "Failed to load dashboard data.");
      } finally {
        setLoading(false);
      }
    };
    fetchDashboard();
  }, []);

  if (loading) return <div className="loading">Loading dashboard...</div>;
  if (error) return <div className="error">{error}</div>;
  if (!dashboardData) return null;

  const { user_data, project_data } = dashboardData;
  const userRole = user_data?.role || "";
  const isAdmin = userRole.includes("administrator");
  const isManager = userRole.includes("manager");
  const isEngineer = userRole.includes("engineer");
  const isClient = userRole.includes("client");

  return isAdmin ? (
    <div className="admin-dashboard-container">
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
    </div>
  ) : (
    <div className="user-dashboard-container">
      {/* Projects Section */}
      <div className="user-projects-section">
        <h2>Project Information</h2>
        {!project_data || project_data.length === 0 ? (
          <p>No projects assigned.</p>
        ) : (
          <div className="user-project-grid">
            {project_data.map((project, index) => (
              <div
                key={project.project_id || index}
                className="user-project-card"
              >
                <h3>
                  {project.project_name} ({project.project_code})
                </h3>
                <p>
                  <strong>Client:</strong> {project.client_name}
                </p>
                <p>
                  <strong>Location:</strong> {project.client_city},{" "}
                  {project.client_country}
                </p>
                <p>
                  <strong>Duration:</strong> {project.start_date} to{" "}
                  {project.end_date}
                </p>

                {/* Manager Role Display */}
                {isManager && (
                  <>
                    <div className="role-specific-info">
                      <h4>Client Manager</h4>
                      <p>
                        <strong>Name:</strong> {project.client_poc || "N/A"}
                      </p>
                      <p>
                        <strong>Email:</strong>{" "}
                        {project.client_poc_email || "N/A"}
                      </p>
                    </div>

                    <div className="role-specific-info">
                      <h4>Task Assignees / Team</h4>
                      {project.task_assignees?.length > 0 ? (
                        <ul className="assignees-list">
                          {project.task_assignees.map((assignee) => (
                            <li key={assignee.user_id}>
                              {assignee.name} ({assignee.email})
                            </li>
                          ))}
                        </ul>
                      ) : (
                        <p>No assignees working on tasks in this project.</p>
                      )}
                    </div>
                  </>
                )}

                {/* Engineer Role Display */}
                {isEngineer && (
                  <div className="role-specific-info">
                    <h4>Project Manager</h4>
                    <p>
                      <strong>Name:</strong> {project.manager_name || "N/A"}
                    </p>
                    <p>
                      <strong>Email:</strong> {project.manager_email || "N/A"}
                    </p>
                  </div>
                )}

                {/* Client Role Display */}
                {isClient && (
                  <div className="role-specific-info">
                    <h4>Point of Contact</h4>
                    <p>
                      <strong>Name:</strong> {project.project_poc || "N/A"}
                    </p>
                    <p>
                      <strong>Email:</strong>{" "}
                      {project.project_poc_email || "N/A"}
                    </p>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
