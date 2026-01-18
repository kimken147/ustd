import { RefineProps } from "@refinedev/core";

const hasPermission = (id: number) => {
    const profile = JSON.parse(
        localStorage.getItem("payment-admin-profile") || "{ permissions: [], role: 0 }",
    ) as Profile;
    return profile.permissions?.find((per) => per.id === id);
};

const accessControlProvider: RefineProps["accessControlProvider"] = {
    can: async ({ resource, action, params }) => {
        const profile = JSON.parse(
            localStorage.getItem("payment-admin-profile") || "{ permissions: [], role: 0 }",
        ) as Profile;

        if (profile.role === 1) return { can: true };

        let can = true;
        if (resource === "sub-accounts" && profile.role !== 1) can = false;

        if (resource === "providers" && action === "create" && !hasPermission(1)) can = false;

        if (resource === "api-white-list" && !hasPermission(25)) can = false;

        if (resource === "merchants") {
            if (action === "create" && !hasPermission(3)) can = false;
            if (action === "4" && !hasPermission(4)) can = false;
            if (action === "18" && !hasPermission(18)) can = false;
            if (action === "30" && !hasPermission(30)) can = false;
        }

        if (resource === "white-list" && !hasPermission(24)) can = false;
        if (resource === "banned-list" && !hasPermission(28)) can = false;

        if (resource === "user-channel-accounts") {
            if (action === "5" && !hasPermission(5)) can = false;
            if (action === "6" && !hasPermission(6)) can = false;
        }

        if (resource === "transactions") {
            if (action === "6" && !hasPermission(6)) can = false;
            if (action === "7" && !hasPermission(7)) can = false;
            if (action === "32" && !hasPermission(32)) can = false;
        }

        if (resource === "statistics/v1" && !hasPermission(33)) can = false;

        if (resource === "withdraws") {
            if (action === "13" && !hasPermission(13)) can = false;
            if (action === "14" && !hasPermission(14)) can = false;
            if (action === "12" && !hasPermission(12)) can = false;
        }

        if (resource === "user-bank-cards" && !hasPermission(14)) can = false;

        if (resource === "channels") {
            if (action === "15" && !hasPermission(15)) can = false;
        }
        if (resource === "feature-toggles" && !hasPermission(16)) can = false;
        if (resource === "SI" && !hasPermission(34)) can = false;

        if ((resource === "thirdchannel" || resource === "merchant-third-channel") && !hasPermission(29)) can = false;

        return {
            can,
            reason: "没有权限",
        };
    },
};

export default accessControlProvider;
