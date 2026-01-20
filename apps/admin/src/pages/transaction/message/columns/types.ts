import type { TableColumnProps } from 'antd';

export interface MessageRecord {
  id: number;
  created_at: string;
  mobile: string;
  content: string;
}

export type MessageColumn = TableColumnProps<MessageRecord>;
