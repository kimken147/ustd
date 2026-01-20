import { DeleteOutlined, EditOutlined, PlusSquareOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { WhitelistedIp } from '@morgan-ustd/shared';
import type { ColumnDependencies, LoginWhiteListColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): LoginWhiteListColumn[] {
  const { t, show, Modal, refetch } = deps;

  return [
    {
      title: t('whitelist.fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('whitelist.fields.loginAccount'),
      dataIndex: 'username',
    },
    {
      title: t('whitelist.fields.loginWhitelist'),
      dataIndex: 'whitelisted_ips',
      render(value: WhitelistedIp[]) {
        return (
          <Space>
            {value?.map(white => (
              <Space key={white.id}>
                <TextField value={white.ipv4} code />
                <Button
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  size="small"
                  onClick={() =>
                    show({
                      title: t('whitelist.actions.editWhitelist'),
                      id: white.id,
                      initialValues: { ipv4: white.ipv4 },
                      onSuccess: refetch,
                      resource: 'whitelisted-ips',
                    })
                  }
                />
                <Button
                  icon={<DeleteOutlined style={{ color: '#6eb9ff' }} />}
                  size="small"
                  onClick={() =>
                    Modal.confirm({
                      id: white.id,
                      resource: 'whitelisted-ips',
                      title: t('whitelist.messages.confirmDelete'),
                      mode: 'delete',
                      onSuccess: refetch,
                    })
                  }
                />
              </Space>
            ))}
          </Space>
        );
      },
    },
    {
      title: t('whitelist.fields.operation'),
      render(_, record) {
        return (
          <Button
            icon={<PlusSquareOutlined />}
            onClick={() =>
              show({
                title: t('whitelist.actions.addWhitelist'),
                id: record.id,
                initialValues: { user_id: record.id, type: 1 },
                mode: 'create',
                resource: 'whitelisted-ips',
                onSuccess: refetch,
              })
            }
          >
            {t('whitelist.actions.add')}
          </Button>
        );
      },
    },
  ];
}
