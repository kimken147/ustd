import { InfoCircleOutlined } from '@ant-design/icons';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Popover, Space } from 'antd';
import type {
  MerchantWalletOperator as Operator,
  MerchantWalletUser as User,
} from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import numeral from 'numeral';
import type { ColumnDependencies, ProviderWalletColumn } from './types';

export type { ColumnDependencies } from './types';

const FORMAT = 'YYYY-MM-DD HH:mm:ss';

export function useColumns(deps: ColumnDependencies): ProviderWalletColumn[] {
  const { t, profileRole } = deps;

  return [
    {
      title: t('balanceAdjustment.providerName'),
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
        let color = '';
        const amount = numeral(value).value();
        if (amount !== null) {
          if (amount > 0) color = 'text-[#16A34A]';
          else if (amount < 0) color = 'text-[#FF4D4F]';
        }
        return <TextField value={value} className={color} />;
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
    },
    {
      title: t('walletHistory.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return <DateField value={value} format={FORMAT} />;
      },
    },
    {
      title: t('walletHistory.operator'),
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
                <TextField value={t('walletHistory.operatorInfo', { name: value.name })} />
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
