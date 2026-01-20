import { DateField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import type { MessageColumn } from './types';

export function useColumns(): MessageColumn[] {
  return [
    {
      title: '建立时间',
      dataIndex: 'created_at',
      render(value) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: '短信账号',
      dataIndex: 'mobile',
    },
    {
      title: '短信内容',
      dataIndex: 'content',
    },
  ];
}
