import { DeleteButton } from '@refinedev/antd';
import dayjs from 'dayjs';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, TagColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): TagColumn[] {
  const { t } = deps;

  return [
    {
      title: t('tagsPage.fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('createAt'),
      dataIndex: 'created_at',
      render(value) {
        return dayjs(value).format(Format);
      },
    },
    {
      title: t('operation'),
      dataIndex: 'id',
      render: id => <DeleteButton recordItemId={id} danger />,
    },
  ];
}
