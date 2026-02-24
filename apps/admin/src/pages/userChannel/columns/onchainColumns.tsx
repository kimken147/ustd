import { SyncOutlined } from '@ant-design/icons';
import { Button, Space } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createOnchainUsdtColumn(deps: ColumnDependencies): UserChannelColumn {
  return {
    title: 'USDT (鏈上)',
    dataIndex: 'onchain_usdt_balance',
    render(value: string, record) {
      return record.channel_code === 'USDT' ? (value ?? '-') : '-';
    },
  };
}

export function createOnchainTrxColumn(deps: ColumnDependencies): UserChannelColumn {
  const { canEdit, onSync } = deps;

  return {
    title: 'TRX (Gas)',
    dataIndex: 'onchain_trx_balance',
    render(value: string, record) {
      if (record.channel_code !== 'USDT') return '-';
      return (
        <Space size={4}>
          <span>{value ?? '-'}</span>
          {canEdit && onSync && (
            <Button
              type="link"
              size="small"
              icon={<SyncOutlined />}
              onClick={() => onSync(record.id)}
            />
          )}
        </Space>
      );
    },
  };
}
