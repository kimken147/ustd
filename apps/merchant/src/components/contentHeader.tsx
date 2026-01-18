import { ArrowLeftOutlined } from "@ant-design/icons";
import { Space, Typography } from "antd";
import { TextField } from "@refinedev/antd";
import { useNavigation } from "@refinedev/core";
import { FC } from "react";

type Props = {
    title: string;
};

const ContentHeader: FC<Props> = ({ title }) => {
    const { goBack } = useNavigation();
    return (
        <Space size={"large"} align="center">
            <ArrowLeftOutlined onClick={goBack} className="text-lg p-2 rounded hover:bg-gray-200" />
            <TextField value={title} strong className="text-xl leading-5" />
        </Space>
    );
};

export default ContentHeader;
