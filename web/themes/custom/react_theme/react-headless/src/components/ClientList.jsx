import React, { useState, useEffect } from "react";
import { getProjectList } from "../api/client";
import "../css/clientlist.css";

export default function ClientList() {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getProjectList()
      .then((response) => setProjects(response.data?.result || []))
      .catch((err) => console.error("Error fetching projects:", err))
      .finally(() => setLoading(false));
  }, []);

	console.log("projects: ", projects);

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
		<div className="client-list-container">
			<h2 className="client-list-header">Clients List</h2>
			<div className="client-cards-grid">
				{projects.map((project, index) => {
					return (
						<div
							key={project.project_id}
							className="clients-card"
						>
							<p>{project.client_name}</p>
						</div>
					);
				})}
			</div>
		</div>
  );
}
