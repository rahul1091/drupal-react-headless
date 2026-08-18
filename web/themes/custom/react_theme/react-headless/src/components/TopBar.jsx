import React, { useState } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import "../css/topbar.css";

export default function TopBar() {
  const { user, logout } = useAuth() || {};
  const navigate = useNavigate();
  const location = useLocation();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const isHomePage = location.pathname === "/";

  const displayName =
    [user?.firstname, user?.lastname].filter(Boolean).join(" ") ||
    user?.name ||
    "User";

  // Get initials from firstname & lastname (or fallback to displayName)
  const getInitials = () => {
    if (user?.firstname || user?.lastname) {
      const firstInitial = user?.firstname ? user.firstname.charAt(0) : "";
      const lastInitial = user?.lastname ? user.lastname.charAt(0) : "";
      return `${firstInitial}${lastInitial}`.toUpperCase() || "U";
    }

    // Fallback: Use the first two characters/words of displayName
    const parts = displayName.trim().split(" ");
    if (parts.length >= 2) {
      return `${parts[0].charAt(0)}${parts[1].charAt(0)}`.toUpperCase();
    }
    return displayName.charAt(0).toUpperCase() || "U";
  };

  const handleLogout = async () => {
    if (isLoggingOut) return;

    try {
      setIsLoggingOut(true);
      if (typeof logout === "function") {
        await logout();
      }
      // Redirect to home and replace browser history to prevent back-button navigation into session
      navigate("/", { replace: true });
    } catch (error) {
      console.error("Logout failed:", error);
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <header className="app-topbar">
      <div className="app-topbar__inner">
        <Link to="/" className="app-topbar__brand">
          🗂 Drupal React CMS
        </Link>

        <nav className="app-topbar__nav">
          {user && isHomePage && (
            <Link to="/dashboard" className="nav-link nav-link--dashboard">
              Go to Dashboard
            </Link>
          )}

          {user ? (
            <div className="app-topbar__user-section">
              <div className="app-topbar__avatar" title={displayName}>
                {getInitials()}
              </div>
              <button
                className="logout-btn"
                onClick={handleLogout}
                disabled={isLoggingOut}
              >
                <svg
                  className="logout-btn__icon"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <polyline points="16 17 21 12 16 7" />
                  <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                {isLoggingOut ? "Logging out..." : "Log out"}
              </button>
            </div>
          ) : (
            <Link to="/login" className="btn-login">
              Sign In
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
