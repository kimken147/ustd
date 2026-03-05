import type { ColumnDependencies, UserChannelColumn } from './types';

export function createChannelColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'channel_name',
    title: t('fields.channel'),
  };
}

