import { useList } from "@refinedev/core";
import { SystemSetting } from "interfaces/systemSetting";

function useSystemSetting() {
    const { result, query } = useList<SystemSetting>({
        resource: "feature-toggles",
        pagination: {
            mode: "off",
        },
    });
    return {
        ...query,
        data: result.data,
    };
}

export default useSystemSetting;
