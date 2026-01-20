import { EditOutlined } from '@ant-design/icons';
import { ShowButton, TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createProviderColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, name, isPaufen, canEdit, showUpdateModal } = deps;

  return {
    title: name,
    width: 120,
    render(_, record) {
      if (isPaufen) {
        return (
          <ShowButton
            icon={null}
            recordItemId={record.user.id}
            resource="providers"
          >
            {record.user.name}
          </ShowButton>
        );
      }
      return (
        <Space>
          <TextField value={record.user.name} />
          <Button
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            disabled={!canEdit}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editProvider', { name }),
                filterFormItems: ['provider_id'],
                initialValues: {
                  provider_id: record.user.id,
                },
                id: record.id,
              });
            }}
          />
        </Space>
      );
    },
  };
}
