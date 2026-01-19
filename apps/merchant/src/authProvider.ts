import type { AuthProvider } from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import dayjs from 'dayjs';
import { apiUrl, cookie } from 'index';

export const TOKEN_KEY = 'merchant_access_token';
export const PROFILE_KEY = 'payment-merchant-profile';

export const getProfile = async () => {
  try {
    const res = await axiosInstance.get<IProfileRes>(`${apiUrl}/me`);
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
        await axiosInstance.get<IProfileRes>(`${apiUrl}/me`);
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
    localStorage.removeItem(PROFILE_KEY);
    return {
      success: true,
      redirectTo: '/login',
    };
  },

  onError: async (error) => {
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
          PROFILE_KEY,
          JSON.stringify({
            agent_enable: profile.agent_enable,
            id: profile.id,
            role: profile.role,
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
    const profile: Pick<Profile, 'agent_enable' | 'id'> = JSON.parse(
      localStorage.getItem(PROFILE_KEY) ?? '{}'
    );
    return profile;
  },

  getIdentity: async () => {
    const token = cookie.get(TOKEN_KEY);
    if (!token) {
      return null;
    }
    return await getProfile();
  },
};
