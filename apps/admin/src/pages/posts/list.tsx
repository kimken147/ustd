import { useMany } from '@refinedev/core';
import { List, useTable, useSelect } from '@refinedev/antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import { FC } from 'react';
import { useColumns, type ColumnDependencies } from './columns';

export const PostList: FC = () => {
  const { tableProps, sorters } = useTable<IPost>({
    dataProviderName: 'test',
    syncWithLocation: true,
    sorters: {
      initial: [{ field: 'id', order: 'desc' }],
    },
  });

  const categoryIds = tableProps?.dataSource?.map((item: IPost) => item.category.id) ?? [];
  const { result: categoriesData, query: categoriesQuery } = useMany<ICategory>({
    resource: 'categories',
    dataProviderName: 'test',
    ids: categoryIds,
    queryOptions: {
      queryKey: ['categories', categoryIds],
      enabled: categoryIds.length > 0,
    },
  });

  const { selectProps: categorySelectProps } = useSelect<ICategory>({
    resource: 'categories',
    dataProviderName: 'test',
  });

  const columnDeps: ColumnDependencies = {
    sorters,
    categoriesData,
    isLoading: categoriesQuery.isLoading,
    categorySelectProps,
  };
  const columns = useColumns(columnDeps);

  return (
    <List>
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
    </List>
  );
};
