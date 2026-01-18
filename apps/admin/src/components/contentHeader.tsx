import { ArrowLeftOutlined } from "@ant-design/icons";
import { Space, TextField } from "@pankod/refine-antd";
import { useNavigation } from "@pankod/refine-core";
import { FC } from "react";

type Props = {
    title: string;
    resource?: string;
};

const ContentHeader: FC<Props> = ({ title, resource }) => {
    const { list, goBack } = useNavigation();
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
