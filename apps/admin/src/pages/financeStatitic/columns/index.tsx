import type { ColumnDependencies, FinanceStatisticColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): FinanceStatisticColumn[] {
  const { t } = deps;

  return [
    {
      title: t('fields.merchantName'),
      dataIndex: 'name',
    },
    {
      title: t('fields.collectionTotal'),
      dataIndex: ['stats', 'daiso', 'total_amount'],
    },
    {
      title: t('fields.collectionCount'),
      dataIndex: ['stats', 'daiso', 'count'],
    },
    {
      title: t('fields.collectionFee'),
      dataIndex: ['stats', 'daiso', 'total_fee'],
    },
    {
      title: t('fields.collectionProfit'),
      dataIndex: ['stats', 'daiso', 'total_profit'],
    },
    {
      title: t('fields.payoutTotal'),
      dataIndex: ['stats', 'daifu', 'total_amount'],
    },
    {
      title: t('fields.payoutCount'),
      dataIndex: ['stats', 'daifu', 'count'],
    },
    {
      title: t('fields.payoutFee'),
      dataIndex: ['stats', 'daifu', 'total_fee'],
    },
    {
      title: t('fields.payoutProfit'),
      dataIndex: ['stats', 'daifu', 'total_profit'],
    },
    {
      title: t('fields.disbursementTotal'),
      dataIndex: ['stats', 'xiafa', 'total_amount'],
    },
    {
      title: t('fields.disbursementCount'),
      dataIndex: ['stats', 'xiafa', 'count'],
    },
    {
      title: t('fields.disbursementFee'),
      dataIndex: ['stats', 'xiafa', 'total_fee'],
    },
    {
      title: t('fields.disbursementProfit'),
      dataIndex: ['stats', 'xiafa', 'total_profit'],
    },
    {
      title: t('fields.platformCollectionProfit'),
      dataIndex: ['stats', 'daiso', 'system_profit'],
    },
    {
      title: t('fields.platformPayoutProfit'),
      dataIndex: ['stats', 'daifu', 'system_profit'],
    },
    {
      title: t('fields.platformDisbursementProfit'),
      dataIndex: ['stats', 'xiafa', 'system_profit'],
    },
  ];
}
