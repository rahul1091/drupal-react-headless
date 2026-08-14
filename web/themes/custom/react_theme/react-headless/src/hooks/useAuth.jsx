import { useState, useEffect, createContext, useContext } from "react";
import { drupalLogin, drupalLogout } from "../api/client";
import { userLogin } from "../api/drupalService";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // If embedded in Drupal, bootstrap from drupalSettings
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
    const drupalUser = await userLogin(email, password);
    setUser({
      id: drupalUser.result.current_user.uid,
      name: drupalUser.result.current_user.username,
      email: drupalUser.result.current_user.email,
      firstname: drupalUser.result.current_user.firstname,
      lastname: drupalUser.result.current_user.lastname,
      role: drupalUser.result.current_user.role,
    });
  };

  const logout = async () => {
    const token = sessionStorage.getItem("csrf_token");
    await drupalLogout(token);
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
