import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import "../css/topbar.css";

export default function TopBar() {
  const { user, logout } = useAuth() || {};
  const navigate = useNavigate();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

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
      <Link to="/" className="app-topbar__brand">
        🗂 Drupal React CMS
      </Link>

      <nav className="app-topbar__nav">
        {user ? (
          <div className="app-topbar__user-section">
            <div className="app-topbar__avatar" title={displayName}>
              {getInitials()}
            </div>
            <button
              className="btn btn-ghost btn-sm"
              onClick={handleLogout}
              disabled={isLoggingOut}
            >
              {isLoggingOut ? "Logging out..." : "Log out"}
            </button>
          </div>
        ) : (
          <Link to="/login" className="btn btn-primary btn-sm">
            Log in
          </Link>
        )}
      </nav>
    </header>
  );
}
