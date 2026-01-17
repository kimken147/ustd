import { useApiUrl, useCustom } from "@pankod/refine-core";

function useProfile() {
    const apiUrl = useApiUrl();
    const { data, ...others } = useCustom<Profile>({
        url: `${apiUrl}/me`,
        method: "get",
    });

    const record = data?.data;
    console.log(record);

    return {
        ...others,
        data: record,
    };
}

export default useProfile;
