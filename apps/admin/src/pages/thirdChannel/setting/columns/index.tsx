import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space, Switch } from 'antd';
import { Gray } from '@morgan-ustd/shared';
import type { ThirdChannelsList } from 'interfaces/merchantThirdChannel';
import type { ColumnDependencies, SettingColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): SettingColumn[] {
  const { t, show, setSelectedThirdChannelId, refetch, UpdateModal } = deps;

  return [
    {
      title: t('fields.merchantName'),
      dataIndex: 'name',
    },
    {
      title: t('fields.loginAccount'),
      dataIndex: 'username',
    },
    {
      title: t('fields.sharedThirdPartyLine'),
      dataIndex: 'include_self_providers',
      render(value, record) {
        return (
          <Switch
            checked={value}
            onChange={checked =>
              UpdateModal.confirm({
                title: t('messages.confirmModifyChannel'),
                id: record.id,
                resource: 'merchants',
                values: {
                  include_self_providers: checked,
                  id: record.id,
                },
                onSuccess() {
                  refetch();
                },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.thirdChannel'),
      dataIndex: 'thirdChannelsList',
      render(value: ThirdChannelsList[]) {
        return (
          <Space>
            {value.map(thirdChannel => (
              <Space key={thirdChannel.id}>
                <TextField
                  value={`${thirdChannel.thirdChannel}(${thirdChannel.channel_code})`}
                  style={{ background: Gray, padding: '5px 10px' }}
                />
                <EditOutlined
                  style={{ color: '#6eb9ff' }}
                  onClick={() => {
                    setSelectedThirdChannelId(thirdChannel.id);
                    show({
                      title: t('actions.editChannel'),
                      id: thirdChannel.id,
                      initialValues: { ...thirdChannel },
                    });
                  }}
                />
                <DeleteOutlined
                  style={{ color: '#ff4d4f' }}
                  onClick={() => {
                    UpdateModal.confirm({
                      title: t('messages.confirmDeleteThirdChannel'),
                      id: thirdChannel.id,
                      mode: 'delete',
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
      title: t('actions.operation'),
      render(_, record) {
        return (
          <Space>
            <Button
              type="primary"
              onClick={() =>
                show({
                  title: t('actions.addThirdChannel'),
                  formValues: { merchant_id: record.id },
                  mode: 'create',
                })
              }
            >
              {t('actions.add')}
            </Button>
          </Space>
        );
      },
    },
  ];
}
