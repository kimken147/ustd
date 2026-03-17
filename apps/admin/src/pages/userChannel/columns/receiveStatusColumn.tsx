import { Tag } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

/**
 * 收款狀態欄位（一次性子地址：待收款/已收款）
 */
export function createReceiveStatusColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;
  return {
    title: t('fields.receiveStatus'),
    dataIndex: 'receive_status',
    width: 90,
    render(value: string, record) {
      if (record.channel_code !== 'USDT' || value === 'none') return '-';
      const color = value === 'unused' ? 'orange' : 'default';
      const text = value === 'unused' ? t('status.unused') : t('status.used');
      return <Tag color={color}>{text}</Tag>;
    },
  };
}
