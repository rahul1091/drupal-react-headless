import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import "../css/login.css";

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();

  const [name, setName] = useState("");
  const [pass, setPass] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!name || !pass) {
      setError("Email and Password are required.");
      return;
    }

    setLoading(true);
    setError("");

    try {
      await login(name, pass);
      navigate("/dashboard");
    } catch (err) {
      setError(err.message || "Login failed. Check your credentials.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-wrapper">
      <div className="login-card">
        <h1>Sign in</h1>
        <p>Use your Drupal account to manage Project Tracker</p>

        {error && <div className="alert alert-error">{error}</div>}

        <form onSubmit={handleSubmit} noValidate>
          <div className="form-group">
            <label className="form-label" htmlFor="login-user">
              Email
            </label>
            <input
              id="login-user"
              type="text"
              className="form-input"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoComplete="username"
              autoFocus
            />
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="login-pass">
              Password
            </label>
            <input
              id="login-pass"
              type="password"
              className="form-input"
              value={pass}
              onChange={(e) => setPass(e.target.value)}
              autoComplete="current-password"
            />
          </div>

          <button
            type="submit"
            className="btn btn-primary login-btn"
            disabled={loading}
          >
            {loading ? <span className="spinner" /> : "Sign in"}
          </button>
        </form>

        {/* --- Registration Section --- */}
        <div className="auth-divider">
          <span>Don't have an account?</span>
        </div>

        <button
          type="button"
          className="btn btn-secondary register-btn"
          onClick={() => navigate("/register")}
        >
          Create New Account
        </button>
      </div>
    </div>
  );
}
