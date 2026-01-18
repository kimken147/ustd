import { Space, Typography } from "antd";
import { TextField } from "@refinedev/antd";
import { FC } from "react";

type Props = {
    color: string;
    text: string;
};

const Badge: FC<Props> = ({ color, text }) => {
    return (
        <Space align="center">
            <div
                className={`w-[6px] h-[6px] relative align-middle rounded-full`}
                style={{ backgroundColor: color }}
            ></div>
            <TextField value={text} style={{ color }} />
        </Space>
    );
};

export default Badge;
