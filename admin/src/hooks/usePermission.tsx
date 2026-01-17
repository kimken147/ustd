import { Permission } from "interfaces/permission";
import useSelector from "./useSelector";

function usePermission() {
    const filterIds = [22];
    // const filterIds: number[] = [];
    const { data, Select } = useSelector<Permission>({
        resource: "permissions",
    });

    const permissions = data?.filter((per) => !filterIds.includes(per.id));

    return {
        permissions,
        Select,
        filterIds,
    };
}

export default usePermission;
