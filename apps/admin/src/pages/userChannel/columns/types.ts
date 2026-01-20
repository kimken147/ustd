import type { TableColumnProps } from 'antd';
import type { ProviderUserChannel as UserChannel, Meta } from '@morgan-ustd/shared';

export type UserChannelColumn = TableColumnProps<UserChannel>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  name: string;
  region: string;
  canEdit: boolean;
  canDelete: boolean;
  isPaufen: boolean;
  dayEnable: boolean;
  monthEnable: boolean;
  getChannelStatusText: (status: number) => string;
  getChannelTypeText: (type: number) => string;
  showUpdateModal: (options: any) => void;
  mutateUserChannel: (options: {
    record: UserChannel;
    values: Record<string, any>;
    title?: string;
    method?: 'put' | 'delete';
  }) => void;
  mutateDeleting: (options: { resource: string; id: number | string }) => void;
}
