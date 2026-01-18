import { SelectOption } from "@morgan-ustd/shared";

export const getStatusOptions: () => SelectOption[] = () => {
    const status: Record<number, string> = {
        0: "停用",
        1: "启用",
    };

    return [null, 0, 1].map((x) => ({
        label: x === null ? "全部" : status[x],
        value: x === null ? "" : x,
    }));
};
