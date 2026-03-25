import { SyncOutlined } from '@ant-design/icons';
import { Button, Space, Tag } from 'antd';
import dayjs from 'dayjs';
import { isUsdtChannel } from 'utils/channel';
import type { ColumnDependencies, UserChannelColumn } from './types';

const chainNetworkLabel: Record<string, string> = {
  trc20: 'TRC-20',
  erc20: 'ERC-20',
  bep20: 'BEP-20',
};

export function createOnchainUsdtColumn(deps: ColumnDependencies): UserChannelColumn {
  const { canEdit, onSync, syncingIds } = deps;

  return {
    title: 'USDT (鏈上)',
    dataIndex: 'onchain_usdt_balance',
    render(value: string, record) {
      if (!isUsdtChannel(record.channel_code)) return '-';
      const isSyncing = syncingIds?.has(record.id) ?? false;
      const network = record.chain_network || 'trc20';
      return (
        <Space size={4}>
          <span>{value ?? '-'}</span>
          <Tag>{chainNetworkLabel[network] ?? network.toUpperCase()}</Tag>
          {canEdit && onSync && (
            <Button
              type="link"
              size="small"
              icon={<SyncOutlined spin={isSyncing} />}
              loading={isSyncing}
              onClick={() => onSync(record.id)}
            />
          )}
        </Space>
      );
    },
  };
}

export function createOnchainSyncedAtColumn(_deps: ColumnDependencies): UserChannelColumn {
  return {
    title: '同步時間',
    dataIndex: 'onchain_synced_at',
    render(value: string, record) {
      if (!isUsdtChannel(record.channel_code)) return '-';
      return value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : '-';
    },
  };
}

export function createOnchainTrxColumn(deps: ColumnDependencies): UserChannelColumn {
  const { canEdit, onSync, syncingIds } = deps;

  return {
    title: 'Gas',
    dataIndex: 'onchain_native_balance',
    render(value: string, record) {
      if (!isUsdtChannel(record.channel_code)) return '-';
      const isSyncing = syncingIds?.has(record.id) ?? false;
      return (
        <Space size={4}>
          <span>{value ?? '-'}</span>
          {canEdit && onSync && (
            <Button
              type="link"
              size="small"
              icon={<SyncOutlined spin={isSyncing} />}
              loading={isSyncing}
              onClick={() => onSync(record.id)}
            />
          )}
        </Space>
      );
    },
  };
}

export function createOnchainEnergyColumn(_deps: ColumnDependencies): UserChannelColumn {
  return {
    title: 'Energy',
    dataIndex: 'onchain_energy_available',
    render(value: number | null, record) {
      if (!isUsdtChannel(record.channel_code)) return '-';
      const network = record.chain_network || record.detail?.chain_network;
      if (network !== 'trc20') return '-';
      if (value == null) return '-';
      const limit = record.onchain_energy_limit ?? 0;
      const isLow = value < 65000;
      return (
        <span style={isLow ? { color: '#ff4d4f' } : undefined}>
          {value.toLocaleString()} / {limit.toLocaleString()}
        </span>
      );
    },
  };
}

export function createOnchainBandwidthColumn(_deps: ColumnDependencies): UserChannelColumn {
  return {
    title: 'Bandwidth',
    dataIndex: 'onchain_bandwidth_available',
    render(value: number | null, record) {
      if (!isUsdtChannel(record.channel_code)) return '-';
      const network = record.chain_network || record.detail?.chain_network;
      if (network !== 'trc20') return '-';
      if (value == null) return '-';
      const limit = record.onchain_bandwidth_limit ?? 0;
      return (
        <span>
          {value.toLocaleString()} / {limit.toLocaleString()}
        </span>
      );
    },
  };
}
