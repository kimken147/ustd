import dayjs from 'dayjs';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, DepositColumn } from './types';

export function createMatchedAtColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.matchedAt'),
    dataIndex: 'matched_at',
    render(value) {
      return value ? dayjs(value).format(Format) : null;
    },
  };
}

export function createCreatedAtColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.createdAt'),
    dataIndex: 'created_at',
    render(value) {
      return dayjs(value).format(Format);
    },
  };
}

export function createConfirmedAtColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.successTime'),
    dataIndex: 'confirmed_at',
    render(value) {
      return value ? dayjs(value).format(Format) : null;
    },
  };
}
