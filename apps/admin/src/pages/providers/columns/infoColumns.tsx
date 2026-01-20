import { EditOutlined } from '@ant-design/icons';
import { ShowButton } from '@refinedev/antd';
import { Button, Space, Tag } from 'antd';
import type { Tag as TagModel } from '@morgan-ustd/shared';
import type { Provider } from 'interfaces/provider';
import type { ColumnDependencies, ProviderColumn } from './types';

export function createNameColumn(deps: ColumnDependencies): ProviderColumn {
  const { t } = deps;

  return {
    title: t('fields.name'),
    dataIndex: 'name',
    render(value, record) {
      return (
        <ShowButton icon={null} recordItemId={record.id.toString()}>
          {value}
        </ShowButton>
      );
    },
  };
}

export function createTagColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, showTagModal } = deps;

  return {
    title: t('fields.tag'),
    dataIndex: 'tags',
    render(value: TagModel[], record) {
      return (
        <Space>
          <Space wrap>
            {value.map(tag => (
              <Tag key={tag.id}>{tag.name}</Tag>
            ))}
          </Space>
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => showTagModal(record)}
          />
        </Space>
      );
    },
  };
}

export function createAgentColumn(deps: ColumnDependencies): ProviderColumn {
  const { t } = deps;

  return {
    title: t('fields.agentName'),
    dataIndex: 'agent',
    render(value: Provider['agent']) {
      if (!value) return '无';
      return (
        <ShowButton icon={null} recordItemId={value.id} resource="providers">
          {value.name}
        </ShowButton>
      );
    },
  };
}
