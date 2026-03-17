import { FC, useMemo, useState, useCallback } from 'react';
import {
  Button,
  Card,
  Descriptions,
  Modal,
  Select,
  Space,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import { useCustom, useCustomMutation } from '@refinedev/core';
import { List } from '@refinedev/antd';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { SyncOutlined } from '@ant-design/icons';
import numeral from 'numeral';
import { apiUrl } from 'index';
// 帳號資料介面（後端回傳格式）
interface AccountRecord {
  id: number;
  account: string;
  name: string;
  channel_code: string;
  address_type?: 'master' | 'child';
  onchain_usdt_balance?: string;
  onchain_native_balance?: string;
  detail?: {
    chain_network?: string;
    [key: string]: any;
  };
  receive_status: 'none' | 'unused' | 'used';
  user?: {
    name: string;
    [key: string]: any;
  };
  parent_account?: {
    id: number;
    account: string;
    name: string;
  } | null;
}

const FundManagementList: FC = () => {
  const { t } = useTranslation('fundManagement');
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [targetAccountId, setTargetAccountId] = useState<number | null>(null);

  // 取得所有 USDT 帳號
  const { query } = useCustom<AccountRecord[]>({
    url: `${apiUrl}/fund-management/accounts`,
    method: 'get',
  });
  const { data: accountsData, isLoading, refetch } = query;

  const accounts: AccountRecord[] = useMemo(
    () => (accountsData?.data as any)?.data ?? accountsData?.data ?? [],
    [accountsData],
  );

  // 批量轉帳 mutation
  const { mutate: batchTransfer, mutation } = useCustomMutation();
  const isTransferring = mutation.isPending;

  // 主地址清單（用於目標帳號下拉）
  const masterAccounts = useMemo(
    () => accounts.filter((acc) => acc.address_type === 'master'),
    [accounts],
  );

  // 目標帳號資料
  const targetAccount = useMemo(
    () => accounts.find((acc) => acc.id === targetAccountId) ?? null,
    [accounts, targetAccountId],
  );

  // 已勾選的帳號
  const selectedAccounts = useMemo(
    () => accounts.filter((acc) => selectedRowKeys.includes(acc.id)),
    [accounts, selectedRowKeys],
  );

  // 合計 USDT
  const selectedTotal = useMemo(
    () =>
      selectedAccounts.reduce(
        (sum, acc) => sum + parseFloat(acc.onchain_usdt_balance ?? '0'),
        0,
      ),
    [selectedAccounts],
  );

  // 預估轉入後餘額
  const afterBalance = useMemo(() => {
    if (!targetAccount) return 0;
    return parseFloat(targetAccount.onchain_usdt_balance ?? '0') + selectedTotal;
  }, [targetAccount, selectedTotal]);

  // 執行批量轉帳
  const handleBatchTransfer = useCallback(() => {
    if (selectedRowKeys.length === 0) {
      message.warning(t('messages.noSelection'));
      return;
    }
    if (!targetAccountId) {
      message.warning(t('messages.noTarget'));
      return;
    }

    Modal.confirm({
      title: t('messages.confirmTransfer', { count: selectedRowKeys.length }),
      okText: t('actions.batchTransfer'),
      onOk: () => {
        batchTransfer(
          {
            url: `${apiUrl}/fund-management/batch-transfer`,
            method: 'post',
            values: {
              source_account_ids: selectedRowKeys,
              target_account_id: targetAccountId,
            },
          },
          {
            onSuccess: () => {
              message.success(
                t('messages.transferSubmitted', { count: selectedRowKeys.length }),
              );
              setSelectedRowKeys([]);
              refetch();
            },
          },
        );
      },
    });
  }, [selectedRowKeys, targetAccountId, batchTransfer, refetch, t]);

  // 表格欄位定義
  const columns = [
    {
      title: t('fields.address'),
      dataIndex: 'account',
      width: 180,
      ellipsis: true,
      render: (v: string) =>
        v ? (
          <Typography.Text copyable={{ text: v }}>
            {v.slice(0, 8)}...{v.slice(-6)}
          </Typography.Text>
        ) : (
          '-'
        ),
    },
    {
      title: t('fields.addressType'),
      dataIndex: 'address_type',
      width: 90,
      render: (v: string) => {
        if (v === 'master') return <Tag color="blue">{t('fields.masterAddress')}</Tag>;
        if (v === 'child') return <Tag color="green">{t('fields.childAddress')}</Tag>;
        return '-';
      },
    },
    {
      title: t('fields.merchantName'),
      dataIndex: 'user',
      width: 120,
      render: (_: any, record: AccountRecord) => record.user?.name ?? '-',
    },
    {
      title: t('fields.parentAddress'),
      dataIndex: 'parent_account',
      width: 160,
      ellipsis: true,
      render: (_: any, record: AccountRecord) => {
        const addr = record.parent_account?.account;
        if (!addr) return '-';
        return (
          <Typography.Text copyable={{ text: addr }}>
            {addr.slice(0, 6)}...{addr.slice(-4)}
          </Typography.Text>
        );
      },
    },
    {
      title: t('fields.usdtBalance'),
      dataIndex: 'onchain_usdt_balance',
      width: 140,
      sorter: (a: AccountRecord, b: AccountRecord) =>
        parseFloat(a.onchain_usdt_balance ?? '0') -
        parseFloat(b.onchain_usdt_balance ?? '0'),
      defaultSortOrder: 'descend' as const,
      render: (v: string) => (
        <Typography.Text strong>
          {numeral(v).format('0,0.00')}
        </Typography.Text>
      ),
    },
    {
      title: t('fields.nativeBalance'),
      dataIndex: 'onchain_native_balance',
      width: 130,
      render: (v: string) => numeral(v).format('0,0.000000'),
    },
    {
      title: t('fields.chainNetwork'),
      dataIndex: ['detail', 'chain_network'],
      width: 100,
      render: (v: string) => {
        if (!v) return '-';
        const labelMap: Record<string, string> = {
          trc20: 'TRC-20',
          erc20: 'ERC-20',
          bep20: 'BEP-20',
        };
        return <Tag>{labelMap[v] ?? v.toUpperCase()}</Tag>;
      },
    },
    {
      title: t('fields.receiveStatus'),
      dataIndex: 'receive_status',
      width: 100,
      render: (v: string) => {
        const colorMap: Record<string, string> = {
          none: 'default',
          unused: 'processing',
          used: 'success',
        };
        return <Tag color={colorMap[v] ?? 'default'}>{t(`status.${v}`)}</Tag>;
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('titles.list')}</title>
      </Helmet>
      <List
        title={t('titles.list')}
        headerButtons={() => (
          <Button
            icon={<SyncOutlined spin={isLoading} />}
            onClick={() => refetch()}
          >
            {t('actions.refreshBalances')}
          </Button>
        )}
      >
        {/* 目標帳號選擇區 */}
        <Card size="small" style={{ marginBottom: 16 }}>
          <Descriptions column={{ xs: 1, sm: 3 }} size="small">
            <Descriptions.Item label={t('fields.targetAccount')}>
              <Select
                style={{ width: 320 }}
                placeholder={t('fields.targetAccount')}
                allowClear
                showSearch
                optionFilterProp="label"
                value={targetAccountId}
                onChange={(val) => setTargetAccountId(val)}
                options={masterAccounts.map((acc) => ({
                  label: `${acc.user?.name ?? ''} - ${acc.account.slice(0, 8)}...${acc.account.slice(-6)}`,
                  value: acc.id,
                }))}
              />
            </Descriptions.Item>
            <Descriptions.Item label={t('fields.currentBalance')}>
              <Typography.Text strong>
                {targetAccount
                  ? numeral(targetAccount.onchain_usdt_balance).format('0,0.00')
                  : '-'}
              </Typography.Text>
            </Descriptions.Item>
            <Descriptions.Item label={t('fields.afterBalance')}>
              <Typography.Text strong type="success">
                {targetAccount ? numeral(afterBalance).format('0,0.00') : '-'}
              </Typography.Text>
            </Descriptions.Item>
          </Descriptions>
        </Card>

        {/* USDT 帳號表格 */}
        <Table
          dataSource={accounts}
          columns={columns}
          rowKey="id"
          loading={isLoading}
          scroll={{ x: 1100 }}
          pagination={false}
          rowSelection={{
            selectedRowKeys,
            onChange: (keys) => setSelectedRowKeys(keys),
            // 排除目標帳號本身不可勾選
            getCheckboxProps: (record: AccountRecord) => ({
              disabled: record.id === targetAccountId,
            }),
          }}
        />

        {/* 底部摘要 + 確認按鈕 */}
        <Card size="small" style={{ marginTop: 16 }}>
          <Space size="large" style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space size="large">
              <Typography.Text>
                {t('fields.selectedCount')}:{' '}
                <Typography.Text strong>{selectedRowKeys.length}</Typography.Text>
              </Typography.Text>
              <Typography.Text>
                {t('fields.selectedTotal')}:{' '}
                <Typography.Text strong>
                  {numeral(selectedTotal).format('0,0.00')} USDT
                </Typography.Text>
              </Typography.Text>
            </Space>
            <Button
              type="primary"
              loading={isTransferring}
              disabled={selectedRowKeys.length === 0 || !targetAccountId}
              onClick={handleBatchTransfer}
            >
              {t('actions.batchTransfer')}
            </Button>
          </Space>
        </Card>
      </List>
    </>
  );
};

export default FundManagementList;
