import { useList } from "@pankod/refine-core";
import { SystemSetting } from "interfaces/systemSetting";

function useSystemSetting() {
    const { data, ...others } = useList<SystemSetting>({
        resource: "feature-toggles",
        config: {
            hasPagination: false,
        },
        queryOptions: {
            refetchOnWindowFocus: false,
        },
    });
    return {
        ...others,
        data: data?.data,
    };
}

export default useSystemSetting;
