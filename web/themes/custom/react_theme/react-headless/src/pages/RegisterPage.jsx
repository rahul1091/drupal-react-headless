import React, { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { registerUser } from "../api/drupalService";
import "../css/register.css";

export default function RegisterPage() {
  const navigate = useNavigate();

  // Form State
  const [formData, setFormData] = useState({
    firstname: "",
    lastname: "",
    email: "",
    password: "",
  });

  // UI State
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);

  // Handle Input Changes
  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // Client-side Validation
  const validateForm = () => {
    const { firstname, lastname, email, password } = formData;

    if (!firstname || !lastname || !email || !password) {
      setError("All fields are required.");
      return false;
    }

    // Password strength requirement matching Drupal backend (Min 8 chars, 1 Upper, 1 Lower, 1 Digit)
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    if (!passwordPattern.test(password)) {
      setError(
        "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.",
      );
      return false;
    }

    return true;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");

    if (!validateForm()) return;

    setLoading(true);

    try {
      const response = await registerUser(formData);

      if (response.data?.status === "Success") {
        setSuccess("Account created successfully! Redirecting to login...");
        setTimeout(() => {
          navigate("/login");
        }, 5000);
      } else {
        setError(response.data?.result || "Registration failed.");
      }
    } catch (err) {
      console.error("Registration Error:", err);
      const apiMessage =
        err.response?.data?.result || err.response?.data?.message;
      setError(apiMessage || "Something went wrong. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="register-wrapper">
      <div className="register-card">
        <h1>Create Account</h1>
        <p>Register a new user account for Project Tracker</p>

        {error && <div className="alert alert-error">{error}</div>}
        {success && <div className="alert alert-success">{success}</div>}

        <form onSubmit={handleSubmit} noValidate>
          {/* First & Last Name Grid */}
          <div className="form-group">
            <label className="form-label" htmlFor="firstname">
              First Name
            </label>
            <input
              id="firstname"
              name="firstname"
              type="text"
              className="form-input"
              value={formData.firstname}
              onChange={handleChange}
              autoFocus
              disabled={loading}
            />
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="lastname">
              Last Name
            </label>
            <input
              id="lastname"
              name="lastname"
              type="text"
              className="form-input"
              value={formData.lastname}
              onChange={handleChange}
              disabled={loading}
            />
          </div>
          {/* Email */}
          <div className="form-group">
            <label className="form-label" htmlFor="email">
              Email Address
            </label>
            <input
              id="email"
              name="email"
              type="email"
              className="form-input"
              value={formData.email}
              onChange={handleChange}
              autoComplete="email"
              disabled={loading}
            />
          </div>

          {/* Password */}
          <div className="form-group">
            <label className="form-label" htmlFor="password">
              Password
            </label>
            <input
              id="password"
              name="password"
              type="password"
              className="form-input"
              value={formData.password}
              onChange={handleChange}
              autoComplete="new-password"
              disabled={loading}
            />
            <small className="form-hint">
              Must be 8+ chars with uppercase, lowercase, and a number.
            </small>
          </div>

          {/* Submit Button */}
          <button
            type="submit"
            className="btn btn-primary register-btn"
            disabled={loading}
          >
            {loading ? <span className="spinner" /> : "Register"}
          </button>
        </form>

        {/* Back to Login Link */}
        <div className="auth-footer">
          <span>Already have an account? </span>
          <Link to="/login" className="auth-link">
            Sign in
          </Link>
        </div>
      </div>
    </div>
  );
}
