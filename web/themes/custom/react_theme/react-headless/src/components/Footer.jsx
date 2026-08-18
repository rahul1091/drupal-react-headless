import React from "react";
import { Link } from "react-router-dom";
import "../css/footer.css";

export default function Footer() {
  const year = new Date().getFullYear();

  return (
    <footer className="app-footer">
      <div className="app-footer__inner">

        <div className="app-footer__brand">
          <span className="app-footer__logo">🗂</span>
          <span className="app-footer__name">Drupal React CMS</span>
          <p className="app-footer__tagline">
            A headless CMS built on Drupal&nbsp;11 and React&nbsp;19.
          </p>
        </div>

        <div className="app-footer__links">
          <div className="app-footer__col">
            <h4>Navigation</h4>
            <ul>
              <li><Link to="/">Home</Link></li>
              <li><Link to="/dashboard">Dashboard</Link></li>
              <li><Link to="/tasks">Task List</Link></li>
            </ul>
          </div>
          <div className="app-footer__col">
            <h4>Account</h4>
            <ul>
              <li><Link to="/login">Log In</Link></li>
              <li><Link to="/register">Register</Link></li>
            </ul>
          </div>
        </div>

      </div>

      <div className="app-footer__bottom">
        <p>
          &copy; {year} Rahul Khan. Built with{" "}
          <a href="https://www.drupal.org" target="_blank" rel="noreferrer">Drupal</a>
          {" "}&amp;{" "}
          <a href="https://react.dev" target="_blank" rel="noreferrer">React</a>.
          All rights reserved.
        </p>
      </div>
    </footer>
  );
}
