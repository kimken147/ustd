import { AuthProvider } from "@pankod/refine-core";
import { axiosInstance } from "@pankod/refine-simple-rest";
import dayjs from "dayjs";
import { apiUrl, cookie } from "index";

export const TOKEN_KEY = "admin_access_token";
export const getProfile = async () => {
    try {
        const res = await axiosInstance.get<IProfileRes>(`${apiUrl}/me`, {
            params: {
                with_stats: 1,
            },
        });
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
                return Promise.resolve();
            } catch (error) {
                return Promise.reject(error);
            }
        }
        return Promise.reject(new Error("username: admin, password: admin"));
    },
    logout: () => {
        cookie.remove(TOKEN_KEY);
        localStorage.removeItem("profile");
        return Promise.resolve();
    },
    checkError: (error) => {
        if (error.status === 401 || error.status === 403) {
            return Promise.reject();
        }
        return Promise.resolve();
    },
    checkAuth: async () => {
        const token = cookie.get(TOKEN_KEY);
        if (token) {
            axiosInstance.defaults.headers.common = {
                Authorization: `Bearer ${token}`,
            };
            const profile = await getProfile();
            localStorage.setItem(
                "payment-admin-profile",
                JSON.stringify({
                    role: profile.role,
                    permissions: profile.permissions || null,
                    id: profile.id,
                }),
            );
            return Promise.resolve();
        }
        return Promise.reject();
    },
    getPermissions: async () => {
        const profile: Pick<Profile, "role" | "permissions" | "id"> = JSON.parse(
            localStorage.getItem("payment-admin-profile") ?? "{}",
        );
        const permissions = profile.permissions;
        return permissions;
    },
    getUserIdentity: async () => {
        const token = cookie.get(TOKEN_KEY);
        if (!token) {
            return Promise.reject();
        }
        return await getProfile();
    },
};
