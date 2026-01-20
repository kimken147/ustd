import type { TableColumnProps } from 'antd';
import type { OnlinMatchingUser } from 'interfaces/onlineMatchingForUser';

export type LiveColumn = TableColumnProps<OnlinMatchingUser>;

export interface ColumnDependencies {
  t: (key: string) => string;
  isPaufen: boolean;
  dayEnable?: boolean;
  monthEnable?: boolean;
  Modal: {
    confirm: (options: {
      title: string;
      id: number;
      resource: string;
      values: Record<string, unknown>;
      onSuccess?: () => void;
    }) => void;
  };
  refetch: () => void;
}
