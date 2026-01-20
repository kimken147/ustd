import {
  TextField,
  getDefaultSortOrder,
  DateField,
  EditButton,
  DeleteButton,
  TagField,
  FilterDropdown,
  ShowButton,
} from '@refinedev/antd';
import { Space, Select } from 'antd';
import type { ColumnDependencies, PostColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): PostColumn[] {
  const { sorters, categoriesData, isLoading, categorySelectProps } = deps;

  return [
    {
      dataIndex: 'id',
      key: 'id',
      title: 'ID',
      render: value => <TextField value={value} />,
      defaultSortOrder: getDefaultSortOrder('id', sorters),
      sorter: true,
    },
    {
      dataIndex: 'title',
      key: 'title',
      title: 'Title',
      render: value => <TextField value={value} />,
      defaultSortOrder: getDefaultSortOrder('title', sorters),
      sorter: true,
    },
    {
      dataIndex: 'status',
      key: 'status',
      title: 'Status',
      render: value => <TagField value={value} />,
      defaultSortOrder: getDefaultSortOrder('status', sorters),
      sorter: true,
    },
    {
      dataIndex: 'createdAt',
      key: 'createdAt',
      title: 'Created At',
      render: value => <DateField value={value} format="LLL" />,
      defaultSortOrder: getDefaultSortOrder('createdAt', sorters),
      sorter: true,
    },
    {
      dataIndex: ['category', 'id'],
      title: 'Category',
      render: value => {
        if (isLoading) {
          return <TextField value="Loading..." />;
        }
        return <TextField value={categoriesData?.data.find(item => item.id === value)?.title} />;
      },
      filterDropdown: props => (
        <FilterDropdown {...props}>
          <Select
            style={{ minWidth: 200 }}
            mode="multiple"
            placeholder="Select Category"
            {...categorySelectProps}
          />
        </FilterDropdown>
      ),
    },
    {
      title: 'Actions',
      dataIndex: 'actions',
      render: (_, record) => (
        <Space>
          <EditButton hideText size="small" recordItemId={record.id} />
          <ShowButton hideText size="small" recordItemId={record.id} />
          <DeleteButton hideText size="small" recordItemId={record.id} />
        </Space>
      ),
    },
  ];
}
