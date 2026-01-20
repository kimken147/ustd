import type { ColumnDependencies, UserChannelColumn } from './types';

export function createChannelColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'channel_name',
    title: t('fields.channel'),
  };
}

export function createBankNameColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'bank_name',
    title: t('fields.bankName'),
  };
}

export function createBankBranchColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'bank_branch',
    title: t('fields.bankBranch'),
    render(value) {
      if (value === 'undefined' || !value) return '';
      return value;
    },
  };
}

export function createBankCardHolderColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: ['detail', 'bank_card_holder_name'],
    title: t('fields.bankCardHolder'),
  };
}
