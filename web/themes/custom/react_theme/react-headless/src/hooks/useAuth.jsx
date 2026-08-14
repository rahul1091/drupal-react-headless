import { useState, useEffect, createContext, useContext } from "react";
import { loginUser, logoutUser } from "../api/client";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // If embedded in Drupal (react_theme.theme -> drupalSettings.reactApp),
    // bootstrap the session from there instead of forcing another login.
    const ds = window.drupalSettings?.reactApp?.currentUser;
    if (ds && !ds.isAnonymous) {
      setUser({
        id: ds.id,
        name: ds.name,
        roles: ds.roles,
        isAdmin: ds.roles?.includes("administrator"),
      });
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    // loginUser() already unwraps the `result` envelope and returns
    // `current_user` directly - do not re-nest it here.
    const currentUser = await loginUser(email, password);
    setUser({
      id: currentUser.uid,
      name: currentUser.username,
      email: currentUser.email,
      firstname: currentUser.firstname,
      lastname: currentUser.lastname,
      role: currentUser.role,
    });
  };

  const logout = async () => {
    await logoutUser();
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
