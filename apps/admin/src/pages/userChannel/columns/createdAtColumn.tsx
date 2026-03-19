import dayjs from 'dayjs';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createCreatedAtColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'created_at',
    title: t('fields.createdAt'),
    width: 160,
    render(value: string) {
      return value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : '-';
    },
  };
}
