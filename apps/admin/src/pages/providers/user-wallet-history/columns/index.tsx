import { EditOutlined, InfoCircleOutlined } from '@ant-design/icons';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Popover, Space } from 'antd';
import type { MerchantWalletOperator as Operator } from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import type { ColumnDependencies, ProviderUserWalletColumn } from './types';

export type { ColumnDependencies } from './types';

const FORMAT = 'YYYY-MM-DD HH:mm:ss';

export function useColumns(deps: ColumnDependencies): ProviderUserWalletColumn[] {
  const { t, profileRole, userId, getUserWalletStatusText, show } = deps;

  return [
    {
      title: t('walletHistory.alterationType'),
      dataIndex: 'type',
      render(value) {
        return getUserWalletStatusText(value);
      },
    },
    {
      title: t('walletHistory.balanceDelta'),
      dataIndex: 'balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('walletHistory.profitDelta'),
      dataIndex: 'profit_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('walletHistory.frozenBalanceDelta'),
      dataIndex: 'frozen_balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('walletHistory.balanceResult'),
      dataIndex: 'balance_result',
    },
    {
      title: t('walletHistory.profitResult'),
      dataIndex: 'profit_result',
    },
    {
      title: t('walletHistory.frozenBalanceResult'),
      dataIndex: 'frozen_balance_result',
    },
    {
      title: t('walletHistory.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: t('walletHistory.editNote'),
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
      title: t('walletHistory.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return value ? <DateField value={value} format={FORMAT} /> : null;
      },
    },
    {
      title: t('walletHistory.operator'),
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
              content={<TextField value={t('walletHistory.operatorInfo', { name: value?.name })} />}
            >
              <InfoCircleOutlined className="text-[#1677ff]" />
            </Popover>
          </Space>
        );
      },
    },
  ];
}
