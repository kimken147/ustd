import { FC, useState } from 'react';
import { Input, Modal, Table } from 'antd';
import { useApiUrl, useCustom } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import numeral from 'numeral';

interface MatchModalProps {
  open: boolean;
  chainTransactionId: number | null;
  onMatch: (transactionId: number) => void;
  onCancel: () => void;
}

// 關聯訂單彈窗：搜尋並選擇要關聯的交易訂單
export const MatchModal: FC<MatchModalProps> = ({ open, chainTransactionId, onMatch, onCancel }) => {
  const { t } = useTranslation('chainTransaction');
  const apiUrl = useApiUrl();
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);

  // 依據訂單號搜尋交易記錄
  const { query } = useCustom({
    url: `${apiUrl}/transactions`,
    method: 'get',
    config: {
      query: {
        order_number: search || undefined,
        per_page: 10,
        'channel_code[]': 'USDT',
      },
    },
    queryOptions: {
      enabled: open && search.length > 0,
    },
  });

  const transactions = (query.data?.data as any)?.data ?? [];
  const isLoading = query.isLoading;

  return (
    <Modal
      title={t('actions.match')}
      open={open}
      onOk={() => selectedId && onMatch(selectedId)}
      onCancel={onCancel}
      okButtonProps={{ disabled: !selectedId }}
      okText={t('actions.ok')}
      cancelText={t('actions.cancel')}
      width={700}
    >
      <Input.Search
        placeholder={t('placeholders.selectTransaction')}
        onSearch={setSearch}
        allowClear
        style={{ marginBottom: 16 }}
      />
      <Table
        loading={isLoading}
        dataSource={transactions}
        rowKey="id"
        size="small"
        pagination={false}
        rowSelection={{
          type: 'radio',
          selectedRowKeys: selectedId ? [selectedId] : [],
          onChange: (keys) => setSelectedId(keys[0] as number),
        }}
        columns={[
          { title: 'ID', dataIndex: 'id', width: 60 },
          { title: t('fields.matchedTransaction'), dataIndex: 'order_number' },
          { title: t('fields.amount'), dataIndex: 'amount', render: (v: string) => numeral(v).format('0,0.00') },
          { title: 'TX Hash', dataIndex: 'tx_hash', ellipsis: true },
        ]}
      />
    </Modal>
  );
};
