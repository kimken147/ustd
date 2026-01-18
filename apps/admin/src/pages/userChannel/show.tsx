import {
  Show,
  TextField,
} from '@refinedev/antd';
import {
  Button,
  Descriptions,
  Switch,
} from 'antd';
import {
  IResourceComponentsProps,
  useNavigation,
  useShow,
} from '@refinedev/core';
import dayjs from 'dayjs';
import useUpdateModal from 'hooks/useUpdateModal';
import { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ChannelStatusChanger } from './component';

const UserChannelShow: FC<IResourceComponentsProps> = props => {
  const { t } = useTranslation('userChannel');
  const { queryResult } = useShow<UserChannel>();
  const { data, isLoading } = queryResult;
  const record = data?.data;
  const { list } = useNavigation();
  const goBack = () => {
    list('user-channel-accounts');
  };

  const { Modal } = useUpdateModal();
  // const { mutateAsync: deleteUserChannel } = useDelete();

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
        </Descriptions>
      </Show>
    </>
  );
};

export default UserChannelShow;
