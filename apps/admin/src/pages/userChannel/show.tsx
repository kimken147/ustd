import {
  Show,
  TextField,
} from '@refinedev/antd';
import {
  Button,
  Descriptions,
  Modal as AntdModal,
  Space,
  Switch,
  Tag,
  Typography,
  message,
} from 'antd';
import {
  IResourceComponentsProps,
  useCustomMutation,
  useNavigation,
  useShow,
} from '@refinedev/core';
import dayjs from 'dayjs';
import useUpdateModal from 'hooks/useUpdateModal';
import { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { apiUrl } from 'index';
import { ChannelStatusChanger } from './component';
import { BatchCreateChildModal } from './components/BatchCreateChildModal';

const UserChannelShow: FC<IResourceComponentsProps> = props => {
  const { t } = useTranslation('userChannel');
  const { query } = useShow<UserChannel>();
  const { data, isLoading, refetch } = query;
  const record = data?.data;
  const { list } = useNavigation();
  const goBack = () => {
    list('user-channel-accounts');
  };

  const { Modal } = useUpdateModal();
  // const { mutateAsync: deleteUserChannel } = useDelete();

  // 批量建立子地址 Modal 狀態
  const [batchModalOpen, setBatchModalOpen] = useState(false);
  // 歸集功能
  const { mutate: consolidate, mutation: consolidateMutation } = useCustomMutation();
  const isConsolidating = consolidateMutation.isPending;

  const handleConsolidate = () => {
    AntdModal.confirm({
      title: t('messages.consolidateConfirm'),
      onOk: () => {
        consolidate({
          url: `${apiUrl}/user-channel-accounts/${record?.id}/consolidate`,
          method: 'post',
          values: {},
        }, {
          onSuccess: () => {
            message.success(t('messages.consolidateSuccess'));
          },
        });
      },
    });
  };

  // 判斷是否為 USDT 主地址
  const isUsdtMaster = record?.channel_code === 'USDT' && record?.address_type === 'master';

  return (
    <>
      <Helmet>
        <title>{t('titles.show')}</title>
      </Helmet>
      <Show
        title={t('titles.show')}
        headerButtons={() => null}
        isLoading={isLoading}
        footerButtons={() => (
          <>
            <Button onClick={goBack}>{t('actions.back')}</Button>
            <Button
              danger
              type="primary"
              onClick={() =>
                Modal.confirm({
                  title: t('confirmation.delete'),
                  id: record?.id || 0,
                  onSuccess: goBack,
                  mode: 'delete',
                })
              }
            >
              {t('actions.delete')}
            </Button>
          </>
        )}
      >
        <Descriptions size="small" bordered column={{ xs: 1, md: 2, lg: 4 }}>
          <Descriptions.Item label={t('fields.merchantName')}>
            {record?.user.name}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.parentAgentName')}>
            {record?.user.agent?.name || t('status.none')}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.createdAt')}>
            {dayjs(record?.created_at).format('YYYY-MM-DD HH:mm:ss')}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.channel')}>
            {record?.channel_name}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.deletedAt')}>
            {record?.deleted_at
              ? dayjs(record.deleted_at).format('YYYY-MM-DD HH:mm:ss')
              : t('status.none')}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.status')}>
            {record && <ChannelStatusChanger record={record} />}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.balance')}>
            <TextField value={record?.balance} />
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.bankName')}>
            <TextField value={record?.bank_name} />
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.accountNumber')}>
            <TextField value={record?.name} />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.dailyLimit')}>
            <Switch
              checked={record?.daily_status}
              onClick={() =>
                Modal.confirm({
                  id: record?.id || 0,
                  values: {
                    daily_status: record?.daily_status ? 0 : 1,
                  },
                  title: t('confirmation.changeDailySwitch'),
                })
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.dailyLimit')}>
            <TextField
              value={`${record?.daily_limit}/${record?.withdraw_daily_limit} ${record?.withdraw_daily_total}/${record?.withdraw_daily_total}`}
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.monthlyLimit')}>
            <Switch
              checked={record?.monthly_status}
              onClick={() =>
                Modal.confirm({
                  id: record?.id || 0,
                  values: {
                    monthly_status: record?.monthly_status ? 0 : 1,
                  },
                  title: t('confirmation.changeMonthlySwitch'),
                })
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.monthlyLimit')}>
            <TextField
              value={`${record?.monthly_limit}/${record?.withdraw_monthly_limit} ${record?.monthly_total}/${record?.withdraw_monthly_total}`}
            />
          </Descriptions.Item>
          {record?.channel_code === 'USDT' && (
            <>
              <Descriptions.Item label={t('fields.account')}>
                <Typography.Text copyable>{record?.account}</Typography.Text>
              </Descriptions.Item>
              <Descriptions.Item label={t('fields.chainNetwork')}>
                {record?.chain_network?.toUpperCase() ?? '-'}
              </Descriptions.Item>
              {/* 地址類型標籤 */}
              <Descriptions.Item label={t('fields.addressType')}>
                <Tag color={record?.address_type === 'master' ? 'blue' : 'green'}>
                  {record?.address_type === 'master'
                    ? t('fields.masterAddress')
                    : t('fields.childAddress')}
                </Tag>
              </Descriptions.Item>
              {/* 子地址顯示母地址連結 */}
              {record?.address_type === 'child' && record?.parent_account && (
                <Descriptions.Item label={t('fields.parentAccount')}>
                  <Link to={`/user-channel-accounts/show/${record.parent_account.id}`}>
                    {record.parent_account.account} ({record.parent_account.name})
                  </Link>
                </Descriptions.Item>
              )}
              {/* 主地址顯示子地址數量 */}
              {record?.address_type === 'master' && (
                <Descriptions.Item label={t('fields.childCount')}>
                  {record?.child_count ?? 0}
                </Descriptions.Item>
              )}
              <Descriptions.Item label={t('fields.onchainUsdtBalance')}>
                {record?.onchain_usdt_balance ?? '-'}
              </Descriptions.Item>
              <Descriptions.Item label={
                record?.chain_network === 'erc20' ? 'ETH (Gas)'
                : record?.chain_network === 'bep20' ? 'BNB (Gas)'
                : 'TRX (Gas)'
              }>
                {record?.onchain_native_balance ?? '-'}
              </Descriptions.Item>
              <Descriptions.Item label={t('fields.lastSyncedAt')}>
                {record?.onchain_synced_at ? dayjs(record.onchain_synced_at).format('YYYY-MM-DD HH:mm:ss') : '-'}
              </Descriptions.Item>
            </>
          )}
        </Descriptions>

        {/* USDT 主地址操作按鈕：批量建立子地址、歸集 */}
        {isUsdtMaster && record && (
          <Space style={{ marginTop: 16 }}>
            <Button type="primary" onClick={() => setBatchModalOpen(true)}>
              {t('actions.batchCreateChild')}
            </Button>
            <Button onClick={handleConsolidate} loading={isConsolidating}>
              {t('actions.consolidateAll')}
            </Button>
          </Space>
        )}

        {/* 批量建立子地址 Modal */}
        {record && (
          <BatchCreateChildModal
            parentAccountId={record.id}
            open={batchModalOpen}
            onClose={() => setBatchModalOpen(false)}
            onSuccess={() => refetch()}
          />
        )}
      </Show>
    </>
  );
};

export default UserChannelShow;
