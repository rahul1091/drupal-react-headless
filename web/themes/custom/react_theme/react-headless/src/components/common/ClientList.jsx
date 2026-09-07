import React, { useState, useEffect } from "react";
import { getClientList } from "../../api/client";
import "../../css/index.css";
import { useTranslation } from "react-i18next";

export default function ClientList() {
	const { t } = useTranslation();
  const [clients, setClients] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getClientList()
      .then((response) => setClients(response.data?.result || []))
      .catch((err) => console.error("Error fetching projects:", err))
      .finally(() => setLoading(false));
  }, []);

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

  if (clients.length === 0) {
    return <div className="no-projects">No Clients Found.</div>;
  }

  return (
		<div className="client-list-container">
			<h2 className="client-list-header">{t("client.clientListTitle")}</h2>
			<div className="client-cards-grid">
				{clients.map((client, index) => {
					return (
						<div
							key={client.project_id}
							className="clients-card"
						>
							<h3>{client.client_name}</h3>
							<p>{client.client_city}, {client.client_country}</p>
						</div>
					);
				})}
			</div>
		</div>
  );
}
