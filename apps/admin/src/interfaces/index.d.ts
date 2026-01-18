interface Meta {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
    provider_todays_amount_enable?: {
        enable: boolean;
    };
}

interface Links {
    first: string;
    last: string;
    prev: any;
    next: string;
}

interface IRes<Data = any, Meta = Meta> {
    data: Data;
    meta?: Meta;
    links?: Links;
}

interface IErrorRes {
    message: string;
}

interface ICategory {
    id: number;
    title: string;
}
interface IPost {
    id: number;
    title: string;
    content: string;
    status: "published" | "draft" | "rejected";
    createdAt: string;
    category: { id: number };
}

declare module "hide-text" {
    const hideText: (
        text: string,
        options?: {
            placeholder?: string;
            showLeft?: number;
            showRight?: number;
            trim?: number;
        },
    ) => string;
    export default hideText;
}
