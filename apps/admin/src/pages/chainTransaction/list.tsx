import { FC, useCallback, useState } from 'react';
import { Button, Col, Input, InputNumber, Modal, Select, Space, Tag, Tooltip, Typography } from 'antd';
import { List, TextField, useTable } from '@refinedev/antd';
import { useApiUrl, useCan, useCustomMutation } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { SyncOutlined, LinkOutlined, EyeInvisibleOutlined, UndoOutlined, EyeOutlined } from '@ant-design/icons';
import numeral from 'numeral';
import dayjs from 'dayjs';
import { ChainTransaction, Resource, ListPageLayout, formValuesToCrudFilters } from '@morgan-ustd/shared';
import { MatchModal } from './components/MatchModal';

const TRONSCAN_BASE = 'https://tronscan.org/#/transaction/';

// 比對狀態對應的標籤顏色
const matchStatusColors: Record<string, string> = {
  pending: 'processing',
  matched: 'success',
  unmatched: 'warning',
  ignored: 'default',
};

const ChainTransactionList: FC = () => {
  const { t } = useTranslation('chainTransaction');
  const apiUrl = useApiUrl();
  const { data: canEdit } = useCan({ action: '37', resource: 'chain-transactions' });

  const [matchModalOpen, setMatchModalOpen] = useState(false);
  const [matchingId, setMatchingId] = useState<number | null>(null);

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<ChainTransaction>({
    resource: Resource.chainTransactions,
    onSearch: formValuesToCrudFilters,
    syncWithLocation: true,
  });

  const { mutate: customMutate, mutation } = useCustomMutation();
  const isSyncing = mutation.isPending;

  // 手動同步所有帳號的鏈上交易
  const handleSync = useCallback(() => {
    Modal.confirm({
      title: t('confirmation.sync'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/sync`,
          method: 'post',
          values: {},
          successNotification: { message: t('messages.syncSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  // 忽略指定鏈上交易
  const handleIgnore = useCallback((id: number) => {
    Modal.confirm({
      title: t('confirmation.ignore'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/${id}/ignore`,
          method: 'put',
          values: {},
          successNotification: { message: t('messages.ignoreSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  // 恢復已忽略的鏈上交易
  const handleRestore = useCallback((id: number) => {
    Modal.confirm({
      title: t('confirmation.restore'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/${id}/restore`,
          method: 'put',
          values: {},
          successNotification: { message: t('messages.restoreSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  // 關聯鏈上交易與系統訂單
  const handleMatch = useCallback((transactionId: number) => {
    if (!matchingId) return;
    customMutate({
      url: `${apiUrl}/chain-transactions/${matchingId}/match`,
      method: 'put',
      values: { transaction_id: transactionId },
      successNotification: { message: t('messages.matchSuccess'), type: 'success' },
    }, {
      onSuccess: () => {
        setMatchModalOpen(false);
        setMatchingId(null);
        refetch();
      },
    });
  }, [apiUrl, customMutate, matchingId, refetch, t]);

  const columns = [
    {
      title: t('fields.blockTimestamp'),
      dataIndex: 'block_timestamp',
      width: 160,
      render: (v: string) => v ? dayjs(new Date(v)).format('YYYY-MM-DD HH:mm:ss') : '-',
    },
    {
      title: t('fields.txHash'),
      dataIndex: 'tx_hash',
      width: 160,
      ellipsis: true,
      render: (v: string) => v ? (
        <Typography.Text copyable={{ text: v }}>
          <a href={`${TRONSCAN_BASE}${v}`} target="_blank" rel="noopener noreferrer">
            {v.slice(0, 8)}...{v.slice(-6)}
          </a>
        </Typography.Text>
      ) : '-',
    },
    {
      title: t('fields.direction'),
      dataIndex: 'direction',
      width: 80,
      render: (v: string) => (
        <Tag color={v === 'in' ? 'green' : 'blue'}>
          {t(`direction.${v}`)}
        </Tag>
      ),
    },
    {
      title: t('fields.amount'),
      dataIndex: 'amount',
      width: 120,
      render: (v: string) => <TextField value={numeral(v).format('0,0.000000')} />,
    },
    {
      title: t('fields.fromAddress'),
      dataIndex: 'from_address',
      width: 140,
      ellipsis: true,
      render: (v: string) => v ? (
        <Typography.Text copyable={{ text: v }}>
          {v.slice(0, 6)}...{v.slice(-4)}
        </Typography.Text>
      ) : '-',
    },
    {
      title: t('fields.toAddress'),
      dataIndex: 'to_address',
      width: 140,
      ellipsis: true,
      render: (v: string) => v ? (
        <Typography.Text copyable={{ text: v }}>
          {v.slice(0, 6)}...{v.slice(-4)}
        </Typography.Text>
      ) : '-',
    },
    {
      title: t('fields.userChannelAccount'),
      dataIndex: 'user_channel_account',
      width: 120,
      render: (_: any, record: ChainTransaction) =>
        record.user_channel_account
          ? record.user_channel_account.name
          : '-',
    },
    {
      title: t('fields.matchStatus'),
      dataIndex: 'match_status',
      width: 100,
      render: (v: string) => (
        <Tag color={matchStatusColors[v]}>
          {t(`matchStatus.${v}`)}
        </Tag>
      ),
    },
    {
      title: t('fields.matchedTransaction'),
      dataIndex: 'matched_transaction',
      width: 140,
      render: (_: any, record: ChainTransaction) =>
        record.matched_transaction
          ? record.matched_transaction.order_number
          : '-',
    },
    {
      title: t('actions.match'),
      width: 150,
      render: (_: any, record: ChainTransaction) => {
        const canOperate = canEdit?.can ?? false;

        // 已匹配：顯示查看訂單按鈕
        if (record.match_status === 'matched') {
          return (
            <Button
              size="small"
              icon={<EyeOutlined />}
              onClick={() => window.open(`/transactions/${record.matched_transaction_id}`, '_blank')}
            >
              {t('actions.viewTransaction')}
            </Button>
          );
        }

        // 已忽略：顯示恢復按鈕
        if (record.match_status === 'ignored') {
          return (
            <Button
              size="small"
              disabled={!canOperate}
              icon={<UndoOutlined />}
              onClick={() => handleRestore(record.id)}
            >
              {t('actions.restore')}
            </Button>
          );
        }

        // 待比對 / 未匹配：顯示關聯與忽略按鈕
        return (
          <Space>
            <Button
              size="small"
              disabled={!canOperate}
              icon={<LinkOutlined />}
              onClick={() => {
                setMatchingId(record.id);
                setMatchModalOpen(true);
              }}
            >
              {t('actions.match')}
            </Button>
            <Button
              size="small"
              disabled={!canOperate}
              icon={<EyeInvisibleOutlined />}
              onClick={() => handleIgnore(record.id)}
            >
              {t('actions.ignore')}
            </Button>
          </Space>
        );
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('titles.pageTitle')}</title>
      </Helmet>
      <List
        headerButtons={() => (
          <Button
            icon={<SyncOutlined spin={isSyncing} />}
            onClick={handleSync}
            disabled={!(canEdit?.can ?? false)}
          >
            {t('actions.sync')}
          </Button>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.matchStatus')} name="match_status">
                <Select
                  allowClear
                  options={[
                    { value: 'pending', label: t('matchStatus.pending') },
                    { value: 'matched', label: t('matchStatus.matched') },
                    { value: 'unmatched', label: t('matchStatus.unmatched') },
                    { value: 'ignored', label: t('matchStatus.ignored') },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.direction')} name="direction">
                <Select
                  allowClear
                  options={[
                    { value: 'in', label: t('direction.in') },
                    { value: 'out', label: t('direction.out') },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.txHash')} name="tx_hash">
                <Input allowClear placeholder={t('placeholders.txHash')} />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.address')} name="address">
                <Input allowClear placeholder={t('placeholders.address')} />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={3}>
              <ListPageLayout.Filter.Item label={t('filters.amountRange')} name="amount_min">
                <InputNumber style={{ width: '100%' }} min={0} placeholder="Min" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={3}>
              <ListPageLayout.Filter.Item label=" " name="amount_max">
                <InputNumber style={{ width: '100%' }} min={0} placeholder="Max" />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          scroll={{ x: 1400 }}
        />
      </List>

      <MatchModal
        open={matchModalOpen}
        chainTransactionId={matchingId}
        onMatch={handleMatch}
        onCancel={() => {
          setMatchModalOpen(false);
          setMatchingId(null);
        }}
      />
    </>
  );
};

export default ChainTransactionList;
