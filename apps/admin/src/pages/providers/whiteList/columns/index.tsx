import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { WhitelistedIp } from '@morgan-ustd/shared';
import type { ColumnDependencies, ProviderWhiteListColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): ProviderWhiteListColumn[] {
  const { t, show, Modal, refetch } = deps;

  return [
    {
      title: t('fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('fields.username'),
      dataIndex: 'username',
    },
    {
      title: t('whiteList.ipv4'),
      dataIndex: 'whitelisted_ips',
      render(value: WhitelistedIp[]) {
        return (
          <Space size="large">
            {value.map(list => (
              <Space key={list.id}>
                <TextField value={list.ipv4} />
                <EditOutlined
                  style={{ color: '#6eb9ff' }}
                  onClick={() => {
                    show({
                      title: t('whiteList.edit'),
                      id: list.id,
                      resource: 'whitelisted-ips',
                      filterFormItems: ['ipv4'],
                      initialValues: { ipv4: list.ipv4 },
                    });
                  }}
                />
                <DeleteOutlined
                  style={{ color: '#ff4d4f' }}
                  onClick={() => {
                    Modal.confirm({
                      title: t('whiteList.confirmDelete'),
                      id: list.id,
                      mode: 'delete',
                      values: {},
                      resource: 'whitelisted-ips',
                      onSuccess: () => refetch(),
                    });
                  }}
                />
              </Space>
            ))}
          </Space>
        );
      },
    },
    {
      title: t('operation', { ns: 'common' }),
      render(_, record) {
        return (
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => {
              show({
                filterFormItems: ['ipv4', 'user_id', 'type'],
                id: record.id,
                title: t('whiteList.add'),
                formValues: { user_id: record.id, type: 1 },
                mode: 'create',
              });
            }}
          >
            {t('create', { ns: 'common' })}
          </Button>
        );
      },
    },
  ];
}
