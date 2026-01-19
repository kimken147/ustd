import { useApiUrl, useCustom } from "@refinedev/core";

function useProfile() {
    const apiUrl = useApiUrl();
    const { result, query } = useCustom<Profile>({
        url: `${apiUrl}/me`,
        method: "get",
    });

    return {
        ...query,
        data: result?.data,
    };
}

export default useProfile;
