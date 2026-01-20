import { Button } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, LiveColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): LiveColumn[] {
  const { t, isPaufen, dayEnable, monthEnable, Modal, refetch } = deps;

  const dayColumns: LiveColumn[] = dayEnable
    ? [
        {
          title: t('fields.dailyCollectionLimit'),
          render(_, record) {
            return `${numeral(record.daily_limit).format('0.00')}/${numeral(record.daily_total).format('0.00')}`;
          },
        },
        {
          title: t('fields.dailyPayoutLimit'),
          render(_, record) {
            return `${numeral(record.withdraw_daily_limit).format('0.00')}/${numeral(record.withdraw_daily_total).format('0.00')}`;
          },
        },
      ]
    : [];

  const monthColumns: LiveColumn[] = monthEnable
    ? [
        {
          title: t('fields.monthlyCollectionLimit'),
          render(_, record) {
            return `${numeral(record.monthly_limit).format('0.00')}/${numeral(record.monthly_total).format('0.00')}`;
          },
        },
        {
          title: t('fields.monthlyPayoutLimit'),
          render(_, record) {
            return `${numeral(record.withdraw_monthly_limit).format('0.00')}/${numeral(record.withdraw_monthly_total).format('0.00')}`;
          },
        },
      ]
    : [];

  const availableBalanceColumn: LiveColumn[] = isPaufen
    ? [{ title: t('fields.availableBalance'), dataIndex: 'available_balance' }]
    : [];

  const balanceColumn: LiveColumn[] = !isPaufen
    ? [
        {
          title: t('fields.balance'),
          dataIndex: 'balance',
          sorter: (a, b) => +a.balance - +b.balance,
        },
      ]
    : [];

  return [
    {
      title: isPaufen ? t('fields.providerAccount') : t('fields.groupAccount'),
      dataIndex: 'name',
    },
    ...availableBalanceColumn,
    {
      title: t('fields.account'),
      dataIndex: 'user_channel_accounts',
    },
    {
      title: t('fields.accountNumber'),
      dataIndex: 'hash_id',
    },
    ...balanceColumn,
    {
      title: t('fields.collectionInProgress'),
      render(_, record) {
        let singleLimit = '';
        if (
          !(record.single_min_limit === null || record.single_min_limit === undefined) ||
          !(record.single_max_limit === null || record.single_max_limit === undefined)
        ) {
          const singleMinLimit =
            record.single_min_limit === null || record.single_min_limit === undefined
              ? ''
              : record.single_min_limit;
          const singleMaxLimit =
            record.single_max_limit === null || record.single_max_limit === undefined
              ? ''
              : record.single_max_limit;
          singleLimit += ` -(${singleMinLimit}~${singleMaxLimit})`;
        }
        return `${record.paying_balance}(${record.total_paying_count})${singleLimit}`;
      },
    },
    {
      title: t('fields.payoutInProgress'),
      render(_, record) {
        return `${record.withdraw_balance}(${record.total_withdraw_count})`;
      },
    },
    ...dayColumns,
    ...monthColumns,
    {
      title: t('fields.operation'),
      render(_, record) {
        return (
          <Button
            danger
            type="primary"
            onClick={() =>
              Modal.confirm({
                title: t('actions.confirmOffline'),
                id: record.user_channel_accounts_id,
                resource: 'user-channel-accounts',
                values: { status: 1 },
                onSuccess: refetch,
              })
            }
          >
            {t('actions.offline')}
          </Button>
        );
      },
    },
  ];
}
