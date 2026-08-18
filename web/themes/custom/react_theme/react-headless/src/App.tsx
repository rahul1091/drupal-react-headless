import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "./hooks/useAuth";

import TopBar from "./components/TopBar";
import Footer from "./components/Footer";
import HomePage from "./pages/HomePage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import DashboardPage from "./pages/DashboardPage";
import TaskList from "./components/TaskList";
import CreateTask from "./components/CreateTask";
import EditTask from "./components/EditTask";
import AddTopic from "./components/AddTopic";

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading)
    return (
      <div className="loading-center">
        <span className="spinner spinner-lg" />
      </div>
    );
  if (!user) return <Navigate to="/login" replace />;
  return children;
}

function AdminRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading)
    return (
      <div className="loading-center">
        <span className="spinner spinner-lg" />
      </div>
    );
  if (!user) return <Navigate to="/login" replace />;
  // Topic creation is admin-only - anyone else who lands here (e.g. via a
  // stale bookmark) is bounced back to their dashboard rather than shown
  // a form that will just 403 on submit.
  if (!user.isAdmin) return <Navigate to="/dashboard" replace />;
  return children;
}

function AppLayout() {
  return (
    <div className="app-layout">
      <TopBar />
      <main className="app-content">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <DashboardPage />
              </ProtectedRoute>
            }
          />

          {/* Task List page - full standalone view */}
          <Route
            path="/tasks"
            element={
              <ProtectedRoute>
                <TaskList />
              </ProtectedRoute>
            }
          />
          <Route
            path="/create-task"
            element={
              <ProtectedRoute>
                <CreateTask />
              </ProtectedRoute>
            }
          />
          <Route
            path="/edit-task/:id"
            element={
              <ProtectedRoute>
                <EditTask />
              </ProtectedRoute>
            }
          />
          <Route
            path="/add-topic"
            element={
              <AdminRoute>
                <AddTopic />
              </AdminRoute>
            }
          />
        </Routes>
      </main>
      <Footer />
    </div>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppLayout />
      </AuthProvider>
    </BrowserRouter>
  );
}
