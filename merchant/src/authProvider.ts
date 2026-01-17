import { AuthProvider } from "@pankod/refine-core";
import { axiosInstance } from "@pankod/refine-simple-rest";
import dayjs from "dayjs";
import { apiUrl, cookie } from "index";

export const TOKEN_KEY = "merchant_access_token";
export const PROFILE_KEY = "payment-merchant-profile";

export const getProfile = async () => {
    try {
        const res = await axiosInstance.get<IProfileRes>(`${apiUrl}/me`);
        return Promise.resolve(res.data.data);
    } catch (error) {
        return Promise.reject(error);
    }
};

const setAuthorization = (token: string, expires: number) => {
    cookie.set(TOKEN_KEY, token, {
        expires: dayjs().add(expires, "second").toDate(),
    });
    axiosInstance.defaults.headers.common = {
        Authorization: `Bearer ${token}`,
    };
};

export const getToken = () => {
    return cookie.get(TOKEN_KEY);
};

export const authProvider: AuthProvider = {
    login: async ({ username, password, googleAuth }) => {
        if (username && password && googleAuth) {
            try {
                const res = await axiosInstance.post<ILoginRes>(`${apiUrl}/login`, {
                    username,
                    password,
                    one_time_password: googleAuth,
                });
                setAuthorization(res.data.data.access_token, res.data.data.expires_in);
                await axiosInstance.get<IProfileRes>(`${apiUrl}/me`);
                return Promise.resolve();
            } catch (error) {
                return Promise.reject(error);
            }
        }
        return Promise.reject(new Error("username: admin, password: admin"));
    },
    logout: () => {
        cookie.remove(TOKEN_KEY);
        localStorage.removeItem(PROFILE_KEY);
        return Promise.resolve();
    },
    checkError: (error) => {
        if (error.status === 401) {
            return Promise.reject();
        }
        return Promise.resolve();
    },
    checkAuth: async () => {
        const token = cookie.get(TOKEN_KEY);
        if (token) {
            try {
                axiosInstance.defaults.headers.common = {
                    Authorization: `Bearer ${token}`,
                };
                const profile = await getProfile();
                localStorage.setItem(
                    PROFILE_KEY,
                    JSON.stringify({
                        agent_enable: profile.agent_enable,
                        id: profile.id,
                        role: profile.role,
                    }),
                );
                return Promise.resolve();
            } catch (error) {
                Promise.reject(error);
            }
        }

        return Promise.reject();
    },
    getPermissions: async () => {
        const profile: Pick<Profile, "agent_enable" | "id"> = JSON.parse(localStorage.getItem(PROFILE_KEY) ?? "{}");
        return profile;
    },
    getUserIdentity: async () => {
        const token = cookie.get(TOKEN_KEY);
        if (!token) {
            return Promise.reject();
        }
        try {
            axiosInstance.defaults.headers.common = {
                Authorization: `Bearer ${token}`,
            };
            const profile = await getProfile();
            return Promise.resolve(profile);
        } catch (error) {
            return Promise.reject(error);
        }
    },
};
