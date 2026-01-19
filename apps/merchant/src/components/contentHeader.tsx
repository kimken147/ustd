import { ArrowLeftOutlined } from "@ant-design/icons";
import { Space, Typography } from "antd";
import { TextField } from "@refinedev/antd";
import { FC } from "react";
import { useNavigate } from "react-router";

type Props = {
    title: string;
};

const ContentHeader: FC<Props> = ({ title }) => {
    const navigate = useNavigate();
    const goBack = () => navigate(-1);
    return (
        <Space size={"large"} align="center">
            <ArrowLeftOutlined onClick={goBack} className="text-lg p-2 rounded hover:bg-gray-200" />
            <TextField value={title} strong className="text-xl leading-5" />
        </Space>
    );
};

export default ContentHeader;
