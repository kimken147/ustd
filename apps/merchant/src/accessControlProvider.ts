import { AccessControlProvider } from "@refinedev/core";
import { PROFILE_KEY } from "authProvider";

const accessControlProvider: AccessControlProvider = {
    can: async ({ resource, action, params }) => {
        let can = true;
        const profile = JSON.parse(
            localStorage.getItem(PROFILE_KEY) || "{ agent_enable: true, id: 0, role: 0 }",
        ) as Profile;

        if (resource === "members") {
            if (profile.role === 5) can = false;
            if (profile.agent_enable === false) can = false;
        }
        if (profile.role === 5) {
            if (resource === "sub-accounts") can = false;
            else if (resource === "withdraws") {
                if (action === "create") can = false;
            } else if (resource === "pay-for-another") can = false;
            else if (resource === "bank-cards") {
                if (action === "create" || action === "edit" || action === "delete") can = false;
            }
        }
        return {
            can,
            reason: "没有权限",
        };
    },
};

export default accessControlProvider;
