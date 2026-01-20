import { InfoCircleOutlined } from '@ant-design/icons';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Popover, Space } from 'antd';
import type {
  MerchantWalletHistory,
  MerchantWalletOperator as Operator,
  MerchantWalletUser as User,
  Format,
} from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import type { ColumnDependencies, WalletHistoryColumn } from './types';

export type { ColumnDependencies } from './types';

const FORMAT = 'YYYY-MM-DD HH:mm:ss';

export function useColumns(deps: ColumnDependencies): WalletHistoryColumn[] {
  const { t, profileRole } = deps;

  return [
    {
      title: t('fields.name'),
      dataIndex: 'user',
      render(value: User) {
        return (
          <ShowButton recordItemId={value.id} icon={null} resource="merchants">
            {value.name}
          </ShowButton>
        );
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
    },
    {
      title: t('wallet.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return <DateField value={value} format={FORMAT} />;
      },
    },
    {
      title: t('wallet.operator'),
      dataIndex: 'operator',
      render(value: Operator) {
        return (
          <Space>
            {value.role === 1 ? (
              <TextField value={value.username} />
            ) : (
              <ShowButton
                recordItemId={value.id}
                disabled={profileRole !== 1}
                resource="sub-accounts"
                icon={null}
              >
                {value.username}
              </ShowButton>
            )}
            <Popover
              trigger={'click'}
              content={
                <TextField value={t('wallet.operatorInfo', { name: value.name })} />
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
