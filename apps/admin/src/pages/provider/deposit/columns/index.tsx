import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { MatchingDepositGroup } from 'interfaces/matchDepositGroup';
import type { ColumnDependencies, DepositGroupColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): DepositGroupColumn[] {
  const { t, tc, name, show, UpdateModal } = deps;

  return [
    {
      title: t('transactionGroup.merchantName'),
      dataIndex: 'name',
    },
    {
      title: t('transactionGroup.username'),
      dataIndex: 'username',
    },
    {
      title: name,
      dataIndex: 'matching_deposit_groups',
      render(value: MatchingDepositGroup[]) {
        return (
          <Space>
            {value.map(group => (
              <Space key={group.id}>
                <TextField
                  value={`${group.personal_enable ? `(${t('transactionGroup.agentLine')})` : ''}${group.provider_name}`}
                  code
                />
                <Button
                  icon={<DeleteOutlined style={{ color: '#ff4d4f' }} />}
                  size="small"
                  onClick={() =>
                    UpdateModal.confirm({
                      title: t('transactionGroup.confirmDelete', { name }),
                      id: group.id,
                      mode: 'delete',
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
      title: tc('operation'),
      render(_, record) {
        return (
          <Button
            icon={<PlusOutlined />}
            type="primary"
            onClick={() =>
              show({
                title: t('transactionGroup.addTitle', { name }),
                initialValues: {
                  merchant_id: record.id,
                  personal_enable: false,
                },
                mode: 'create',
                confirmTitle: t('transactionGroup.confirmAdd', { name }),
                successMessage: t('transactionGroup.addSuccess'),
              })
            }
          >
            {t('transactionGroup.add')}
          </Button>
        );
      },
    },
  ];
}
