import React, { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../../hooks/useAuth";
import { getProjectDetails } from "../../api/client";
import "../../css/index.css";
import { useTranslation } from "react-i18next";

export default function ProjectList() {
	const { t } = useTranslation();
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);

  const navigate = useNavigate();
  const { user } = useAuth();
  const isAdmin = !!user?.isAdmin;
  const projectsPerPage = 5;

  useEffect(() => {
    getProjectDetails()
      .then((response) => setProjects(response.data?.result || []))
      .catch((err) => console.error("Error fetching projects:", err))
      .finally(() => setLoading(false));
  }, []);

  // Pagination calculations
  const totalPages = Math.ceil(projects.length / projectsPerPage);
  const indexOfLastProject = currentPage * projectsPerPage;
  const indexOfFirstProject = indexOfLastProject - projectsPerPage;

  const currentProjects = projects.slice(
    indexOfFirstProject,
    indexOfLastProject,
  );

  const formatBudget = (budget) => {
    if (budget === null || budget === undefined || budget === "") {
      return "Not available";
    }

    const numericBudget = Number(budget);

    if (Number.isNaN(numericBudget)) {
      return budget;
    }

    return new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency: "USD",
      maximumFractionDigits: 2,
    }).format(numericBudget);
  };

  if (loading) {
    return (
      <div className="skeleton-grid">
        {[...Array(8)].map((_, i) => (
          <div key={i} className="skeleton-card">
            <div className="skeleton-line" />
          </div>
        ))}
      </div>
    );
  }

  if (projects.length === 0) {
    return <div className="no-projects">No Projects Found.</div>;
  }

  return (
    <>
      <div className="breadcrumb">
        <button
          type="button"
          className="back-to-dashboard-link"
          onClick={() => navigate("/dashboard")}
        >
          ← Dashboard
        </button>
        <span className="breadcrumb-sep">/</span>
        <span className="breadcrumb-location">Project List</span>
      </div>
    <div className="form-card-wrapper">
      <div className="form-header">
        <div>
          <h1 className="form-title">{t("project.projectListTitle")}</h1>
          <p className="project-count">
            {t("project.projectCount")}: {projects.length}
          </p>
        </div>

        <button
          className="btn-admin add-project-btn"
          onClick={() => navigate("/add-project")}
        >
          + {t("project.addProjectButton")}
        </button>
      </div>

      <div className="project-list-content">
        {currentProjects.map((project, index) => {
          return (
            <div
              key={project.id || project.nid || index}
              className="project-card"
            >
              <div className="project-card-header">
                <div className="project-heading">
                  <p className="project-label">{t("project.projectLabel")}</p>
                  <h2 className="project-title">
                    {project.project_details.title}
                  </h2>
                </div>

                <p className="project-code">
                  {project.project_details.project_code}
                </p>
              </div>

							<div className="project-summary">
								<div className="project-info-item">
                  <p className="project-info-label">{t("project.clientName")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_name}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientAddress")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_address}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientCity")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_city}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientCountry")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_country}
                  </p>
                </div>
              </div>

							<div className="project-summary">
                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientPOC")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_poc.fullname}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientPOCEmail")}</p>
                  <p className="project-info-value">
                    {project.client_details.client_poc.mail}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.clientBudget")}</p>
                  <p className="project-info-value project-budget">
                    {formatBudget(project.client_details.client_budget)}
                  </p>
                </div>
              </div>

              <div className="project-summary">
                <div className="project-info-item">
                  <p className="project-info-label">{t("project.projectManager")}</p>
                  <p className="project-info-value">
                    {project.project_details.project_manager?.fullname}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.projectStartDate")}</p>
                  <p className="project-info-value">
                    {project.project_details.start_date}
                  </p>
                </div>

                <div className="project-info-item">
                  <p className="project-info-label">{t("project.projectEndDate")}</p>
                  <p className="project-info-value">
                    {project.project_details.end_date}
                  </p>
                </div>

								<div className="project-info-item">
                  <p className="project-info-label">{t("project.projectCreated")}</p>
                  <p className="project-info-value project-created">
                    {project.created}
                  </p>
                </div>
              </div>

              <div className="project-description">
                <div className="project-info-item">
                  <p className="project-info-label">{t("project.projectDescription")}</p>
                  <p className="project-info-value">
                    {project.project_details.description}
                  </p>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {projects.length > projectsPerPage && (
        <div className="pagination-wrapper">
          <div className="pagination">
            <button
              className="pagination-btn"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage((prev) => prev - 1)}
            >
              ⬅️
            </button>
            {Array.from({ length: totalPages }, (_, index) => (
              <button
                key={index + 1}
                className={`pagination-btn ${
                  currentPage === index + 1 ? "active" : ""
                }`}
                onClick={() => setCurrentPage(index + 1)}
              >
                {index + 1}
              </button>
            ))}
            <button
              className="pagination-btn"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage((prev) => prev + 1)}
            >
              ➡️
            </button>
          </div>

          <div className="page-info">
            Showing {indexOfFirstProject + 1} -{" "}
            {Math.min(indexOfLastProject, projects.length)} of {projects.length}{" "}
            projects
          </div>
        </div>
      )}
    </div>
    </>
  );
}
