import type { TableColumnProps } from 'antd';
import type { ThirdChannel } from 'interfaces/thirdChannel';

export type ThirdChannelColumn = TableColumnProps<ThirdChannel>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  show: (options: any) => void;
  feeShow: (options: any) => void;
  setSelectedThirdChannel: (record: ThirdChannel | null) => void;
}
