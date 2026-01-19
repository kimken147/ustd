import { IResourceComponentsProps, useOne, useShow } from "@refinedev/core";
import {
  Show,
  MarkdownField,
} from '@refinedev/antd';
import {
  Typography,
  Tag,
} from 'antd';

const { Title, Text } = Typography;

export const PostShow: React.FC<IResourceComponentsProps> = () => {
    const { query } = useShow<IPost>({
        dataProviderName: "test",
    });
    const { data, isLoading } = query;
    const record = data?.data;

    const { result: categoryData } = useOne<ICategory>({
        resource: "categories",
        id: record?.category.id ?? "",
        queryOptions: {
            queryKey: ['category', record?.category.id],
            enabled: !!record?.category.id,
        },
        dataProviderName: "test",
    });

    return (
        <Show isLoading={isLoading}>
            <Title level={5}>Title</Title>
            <Text>{record?.title}</Text>

            <Title level={5}>Status</Title>
            <Text>
                <Tag>{record?.status}</Tag>
            </Text>

            <Title level={5}>Category</Title>
            <Text>{categoryData?.title}</Text>

            <Title level={5}>Content</Title>
            <MarkdownField value={record?.content} />
        </Show>
    );
};
