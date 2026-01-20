import { EditOutlined, InfoCircleOutlined } from '@ant-design/icons';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Popover, Space } from 'antd';
import type { MerchantWalletOperator as Operator } from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import type { ColumnDependencies, UserWalletHistoryColumn } from './types';

export type { ColumnDependencies } from './types';

const FORMAT = 'YYYY-MM-DD HH:mm:ss';

export function useColumns(deps: ColumnDependencies): UserWalletHistoryColumn[] {
  const { t, profileRole, userId, getUserWalletStatusText, show } = deps;

  return [
    {
      title: t('filters.alterationType'),
      dataIndex: 'type',
      render(value) {
        return getUserWalletStatusText(value);
      },
    },
    {
      title: t('wallet.balanceDelta'),
      dataIndex: 'balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('wallet.frozenBalanceDelta'),
      dataIndex: 'frozen_balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('wallet.balanceResult'),
      dataIndex: 'balance_result',
    },
    {
      title: t('wallet.frozenBalanceResult'),
      dataIndex: 'frozen_balance_result',
    },
    {
      title: t('wallet.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: t('wallet.editNote'),
                  id: record.id,
                  resource: `users/${userId}/wallet-histories`,
                  filterFormItems: ['note'],
                  initialValues: { note: record.note },
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('wallet.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return value ? <DateField value={value} format={FORMAT} /> : null;
      },
    },
    {
      title: t('wallet.operator'),
      dataIndex: 'operator',
      render(value: Operator) {
        if (!value) return null;
        return (
          <Space>
            {value?.role === 1 ? (
              <TextField value={value?.username} />
            ) : (
              <ShowButton
                recordItemId={value?.id}
                disabled={profileRole !== 1}
                resource="sub-accounts"
                icon={null}
              >
                {value?.username}
              </ShowButton>
            )}
            <Popover
              trigger={'click'}
              content={
                <TextField value={t('wallet.operatorInfo', { name: value?.name })} />
              }
            >
              <InfoCircleOutlined className="text-[#1677ff]" />
            </Popover>
          </Space>
        );
      },
    },
  ];
}
