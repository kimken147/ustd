import { DateField, TextField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import numeral from 'numeral';
import type { ColumnDependencies, WalletHistoryColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): WalletHistoryColumn[] {
  const { t } = deps;

  return [
    {
      title: t('walletHistory.fields.alterationCategories'),
      dataIndex: 'type',
      render: value => <TextField value={t(`walletHistory.status.${value}`)} />,
    },
    {
      title: t('walletHistory.fields.totalBalanceAlteration'),
      dataIndex: 'balance_delta',
      render(value) {
        const amount = numeral(value).value() ?? 1;
        return <TextField value={value} className={amount > 0 ? 'text-blue-500' : 'text-red-500'} />;
      },
    },
    {
      title: t('walletHistory.fields.frozenBalanceAlteration'),
      dataIndex: 'frozen_balance_delta',
    },
    {
      title: t('walletHistory.fields.totalBalanceAfterAlteration'),
      dataIndex: 'balance_result',
    },
    {
      title: t('walletHistory.fields.frozenBalanceAfterAlteration'),
      dataIndex: 'frozen_balance_result',
    },
    {
      title: t('note'),
      dataIndex: 'note',
    },
    {
      title: t('walletHistory.fields.alterationTime'),
      dataIndex: 'created_at',
      render: value => <DateField value={value} format={Format} />,
    },
  ];
}
