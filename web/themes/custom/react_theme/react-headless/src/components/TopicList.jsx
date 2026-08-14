import { useEffect, useState } from "react";
import { getTopics } from "../api/client";
import "../css/topiclist.css";

// Helper to strip HTML tags for accurate character counting
const stripHtml = (html) => {
  const doc = new DOMParser().parseFromString(html, "text/html");
  return doc.body.textContent || "";
};

function TopicList() {
  const [topics, setTopics] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedTopic, setSelectedTopic] = useState(null);

  const CHARACTER_LIMIT = 250;

  useEffect(() => {
    getTopics()
      .then((response) => setTopics(response.data.result))
      .catch((error) => console.error("Error fetching topics:", error))
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

  if (topics.length === 0) {
    return <div className="no-topics">No topics found.</div>;
  }

  return (
    <div className="topic-list-container">
      <h2 className="topic-list-heading">Topic List</h2>
      <div className="topic-grid">
        {topics.map((topic, index) => {
          const rawDescription = topic.description || "";
          const plainText = stripHtml(rawDescription);
          const isLongText = plainText.length > CHARACTER_LIMIT;

          const truncatedText = isLongText
            ? plainText.substring(0, CHARACTER_LIMIT) + "..."
            : plainText;

          return (
            <div key={topic.id || index} className="topic-card">
              <h3 className="topic-title">{topic.title}</h3>
              {topic.subheading && (
                <h4 className="topic-subheading">{topic.subheading}</h4>
              )}
              {rawDescription && (
                <div className="topic-body">
                  <p className="topic-description-text">{truncatedText}</p>
                  {isLongText && (
                    <button
                      onClick={() => setSelectedTopic(topic)}
                      className="read-more-btn"
                    >
                      Read More
                    </button>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* --- MODAL --- */}
      {selectedTopic && (
        <div className="modal-overlay" onClick={() => setSelectedTopic(null)}>
          <div
            className="modal-content"
            onClick={(e) => e.stopPropagation()} // Prevents closing when clicking inside
          >
            <button
              onClick={() => setSelectedTopic(null)}
              className="modal-close-btn"
              aria-label="Close modal"
            >
              &times;
            </button>
            <h2 className="modal-title">{selectedTopic.title}</h2>
            {selectedTopic.subheading && (
              <h3 className="modal-subheading">{selectedTopic.subheading}</h3>
            )}
            <hr className="modal-divider" />
            <div
              className="modal-body"
              dangerouslySetInnerHTML={{ __html: selectedTopic.description }}
            />
            <div className="modal-trending">
              {selectedTopic.trending && (
                <p>
                  Trending Status: <span>{selectedTopic.trending}</span>
                </p>
              )}
            </div>
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

export default TopicList;
