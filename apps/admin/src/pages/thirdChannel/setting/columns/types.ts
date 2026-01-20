import type { TableColumnProps } from 'antd';
import type { MerchantThirdChannel } from 'interfaces/merchantThirdChannel';

export type SettingColumn = TableColumnProps<MerchantThirdChannel>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  show: (options: any) => void;
  setSelectedThirdChannelId: (id: number) => void;
  refetch: () => void;
  UpdateModal: {
    confirm: (options: any) => void;
  };
}
