import { useState, useEffect, createContext, useContext } from "react";

import { loginUser, logoutUser } from "../api/client";

/**
 * @typedef {Object} AuthUser
 * @property {string|number} [id]
 * @property {string} [name]
 * @property {string} [email]
 * @property {string} [firstname]
 * @property {string} [lastname]
 * @property {string} [role]
 * @property {string[]} [roles]
 * @property {boolean} [isAdmin]
 * @property {string|number} [created]
 */

/**
 * @typedef {Object} AuthContextValue
 * @property {AuthUser|null} user
 * @property {boolean} loading
 * @property {(email: string, password: string) => Promise<void>} login
 * @property {() => Promise<void>} logout
 */

/**
 * @type {import("react").Context<AuthContextValue|null>}
 */
const AuthContext = createContext(null);

/**
 * @param {{ children: import("react").ReactNode }} props
 */
export function AuthProvider({ children }) {
  /** @type {[AuthUser|null, import("react").Dispatch<import("react").SetStateAction<AuthUser|null>>]} */
  const [user, setUser] = useState(null);

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // When embedded in Drupal, bootstrap the authenticated user
    // from drupalSettings instead of requesting another login.
    const currentUser = window.drupalSettings?.reactApp?.currentUser;

    if (currentUser && !currentUser.isAnonymous) {
      setUser({
        id: currentUser.id,
        name: currentUser.name,
        roles: currentUser.roles || [],
        isAdmin: currentUser.roles?.includes("administrator") || false,
      });
    }

    setLoading(false);
  }, []);

  /**
   * @param {string} email
   * @param {string} password
   * @returns {Promise<void>}
   */
  const login = async (email, password) => {
    const currentUser = await loginUser(email, password);

    setUser({
      id: currentUser.uid,
      name: currentUser.username,
      email: currentUser.email,
      firstname: currentUser.firstname,
      lastname: currentUser.lastname,
      role: currentUser.role,
      isAdmin: Boolean(currentUser.isAdmin),
      created: currentUser.created,
    });
  };

  /**
   * @returns {Promise<void>}
   */
  const logout = async () => {
    await logoutUser();
    setUser(null);
  };

  /** @type {AuthContextValue} */
  const contextValue = {
    user,
    loading,
    login,
    logout,
  };

  return (
    <AuthContext.Provider value={contextValue}>{children}</AuthContext.Provider>
  );
}

/**
 * @returns {AuthContextValue}
 */
export function useAuth() {
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error("useAuth must be used within an AuthProvider.");
  }

  return context;
}
