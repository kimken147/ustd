import { ArrowLeftOutlined } from "@ant-design/icons";
import { Space } from "antd";
import { TextField } from "@refinedev/antd";
import { useNavigation } from "@refinedev/core";
import { useNavigate } from "react-router";
import { FC } from "react";

type Props = {
    title: string;
    resource?: string;
};

const ContentHeader: FC<Props> = ({ title, resource }) => {
    const { list } = useNavigation();
    const navigate = useNavigate();
    const goBack = () => navigate(-1);
    return (
        <Space size={"large"} align="center">
            <ArrowLeftOutlined
                onClick={() => (resource ? list(resource) : goBack())}
                className="text-lg p-2 rounded hover:bg-gray-200"
            />
            <TextField value={title} strong className="text-xl leading-5" />
        </Space>
    );
};

export default ContentHeader;
