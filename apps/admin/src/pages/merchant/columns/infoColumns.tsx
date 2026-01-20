import { EditOutlined } from '@ant-design/icons';
import { ShowButton } from '@refinedev/antd';
import { Button, Space, Tag } from 'antd';
import type { Merchant, Tag as TagModel } from '@morgan-ustd/shared';
import type { ColumnDependencies, MerchantColumn } from './types';

export function createNameColumn(deps: ColumnDependencies): MerchantColumn {
  const { t } = deps;

  return {
    title: t('fields.name'),
    dataIndex: 'name',
    render(value, record) {
      return (
        <ShowButton recordItemId={record.id} icon={null}>
          {value}
        </ShowButton>
      );
    },
  };
}

export function createTagColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, showTagModal } = deps;

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
            disabled={!canEditProfile}
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => showTagModal(record)}
          />
        </Space>
      );
    },
  };
}

export function createAgentColumn(deps: ColumnDependencies): MerchantColumn {
  const { t } = deps;

  return {
    title: t('fields.agentName'),
    dataIndex: 'agent',
    render(agent: Merchant) {
      return agent ? (
        <ShowButton recordItemId={agent.id} icon={null} resource="merchants">
          {agent.name}
        </ShowButton>
      ) : (
        t('status.none')
      );
    },
  };
}
