import { Tag } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

/**
 * 地址類型欄位（主地址 / 子地址）
 * 僅對 USDT 通道有意義，其他通道顯示「-」
 */
export function createAddressTypeColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    title: t('fields.addressType'),
    dataIndex: 'address_type',
    width: 80,
    render(value: string, record) {
      if (record.channel_code !== 'USDT') return '-';
      return (
        <Tag color={value === 'master' ? 'blue' : 'green'}>
          {value === 'master' ? t('fields.masterAddress') : t('fields.childAddress')}
        </Tag>
      );
    },
  };
}
