import type { TableColumnProps } from 'antd';
import type { Channel } from '@morgan-ustd/shared';

export type ChannelColumn = TableColumnProps<Channel>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  canEdit: boolean;
  apiUrl: string;
  show: (options: any) => void;
  Modal: {
    confirm: (options: any) => void;
  };
  refetch: () => void;
}
