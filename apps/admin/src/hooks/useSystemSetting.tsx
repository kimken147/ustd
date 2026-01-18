import { useList } from "@refinedev/core";
import { SystemSetting } from "interfaces/systemSetting";

function useSystemSetting() {
    const { data, ...others } = useList<SystemSetting>({
        resource: "feature-toggles",
        pagination: {
            mode: "off",
        },
    });
    return {
        ...others,
        data: data?.data,
    };
}

export default useSystemSetting;
