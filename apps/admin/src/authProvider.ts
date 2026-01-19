import type { AuthProvider } from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import dayjs from 'dayjs';
import { apiUrl, cookie } from 'index';

export const TOKEN_KEY = 'admin_access_token';

export const getProfile = async () => {
  try {
    const res = await axiosInstance.get<IProfileRes>(`${apiUrl}/me`, {
      params: {
        with_stats: 1,
      },
    });
    return res.data.data;
  } catch (error: any) {
    throw error;
  }
};

const setAuthorization = (token: string, expires: number) => {
  cookie.set(TOKEN_KEY, token, {
    expires: dayjs().add(expires, 'second').toDate(),
  });
  axiosInstance.defaults.headers.common = {
    Authorization: `Bearer ${token}`,
  };
};

export const getToken = () => {
  return cookie.get(TOKEN_KEY);
};

export const authProvider: AuthProvider = {
  login: async ({ username, password, googleAuth }: any) => {
    if (username && password && googleAuth) {
      try {
        const res = await axiosInstance.post<ILoginRes>(`${apiUrl}/login`, {
          username,
          password,
          one_time_password: googleAuth,
        });
        setAuthorization(res.data.data.access_token, res.data.data.expires_in);
        return {
          success: true,
          redirectTo: '/',
        };
      } catch (error: any) {
        return {
          success: false,
          error: {
            name: 'LoginError',
            message: error?.response?.data?.message || error?.message || 'Login failed',
          },
        };
      }
    }
    return {
      success: false,
      error: {
        name: 'LoginError',
        message: 'Please provide username, password and google auth code',
      },
    };
  },

  logout: async () => {
    cookie.remove(TOKEN_KEY);
    localStorage.removeItem('profile');
    localStorage.removeItem('payment-admin-profile');
    return {
      success: true,
      redirectTo: '/login',
    };
  },

  onError: async error => {
    if (error.status === 401 || error.status === 403) {
      return {
        logout: true,
        redirectTo: '/login',
        error,
      };
    }
    return { error };
  },

  check: async () => {
    const token = cookie.get(TOKEN_KEY);
    if (token) {
      axiosInstance.defaults.headers.common = {
        Authorization: `Bearer ${token}`,
      };
      try {
        const profile = await getProfile();
        localStorage.setItem(
          'payment-admin-profile',
          JSON.stringify({
            role: profile.role,
            permissions: profile.permissions || null,
            id: profile.id,
          })
        );
        return {
          authenticated: true,
        };
      } catch {
        return {
          authenticated: false,
          redirectTo: '/login',
        };
      }
    }
    return {
      authenticated: false,
      redirectTo: '/login',
    };
  },

  getPermissions: async () => {
    const profile: Pick<Profile, 'role' | 'permissions' | 'id'> = JSON.parse(
      localStorage.getItem('payment-admin-profile') ?? '{}'
    );
    return profile.permissions;
  },

  getIdentity: async () => {
    const token = cookie.get(TOKEN_KEY);
    if (!token) {
      return null;
    }
    return await getProfile();
  },
};
