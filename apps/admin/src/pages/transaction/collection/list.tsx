/**
 * Collection List Page - 重構後版本
 *
 * 使用 Refine useTable + ListPageLayout + shared hooks + modular columns
 */
import { FC, useState } from 'react';
import {
  Button,
  Col,
  DatePicker,
  Divider,
  Input,
  InputNumber,
  Radio,
  Select,
} from 'antd';
import type { CrudFilter } from '@refinedev/core';
import {
  CreateButton,
  List,
  useModal,
  useTable,
} from '@refinedev/antd';
import {
  useApiUrl,
  useCan,
  useCustom,
  useCustomMutation,
  useGetIdentity,
  useList,
} from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import dayjs, { Dayjs } from 'dayjs';
import numeral from 'numeral';
import queryString from 'query-string';
import {
  ExportOutlined,
  PlayCircleOutlined,
  PlusOutlined,
} from '@ant-design/icons';

import {
  ListPageLayout,
  useTransactionStatus,
  useTransactionCallbackStatus,
  useUpdateModal,
  useSelector,
} from '@morgan-ustd/shared';
import type {
  Transaction,
  TransactionMeta as Meta,
  TransactionStat as Stat,
} from '@morgan-ustd/shared';

import useProvider from 'hooks/useProvider';
import useMerchant from 'hooks/useMerchant';
import useChannel from 'hooks/useChannel';
import useChannelGroup from 'hooks/useChannelGroup';
import useUserChannelAccount from 'hooks/useUserChannelAccount';
import useAutoRefetch from 'hooks/useAutoRefetch';
import CustomDatePicker from 'components/customDatePicker';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { SystemSetting } from 'interfaces/systemSetting';
import Env from 'lib/env';

import { useColumns } from './columns';
import type { ColumnDependencies } from './columns';
import StatisticsCards from './StatisticsCards';
import CreateTransactionModal from './CreateTransactionModal';

const CollectionList: FC = () => {
  const { t } = useTranslation('transaction');
  const apiUrl = useApiUrl();
  const isPaufen = Env.isPaufen;
  const groupLabel = isPaufen ? t('fields.group') : t('fields.groupName');

  const defaultStartAt = dayjs().startOf('days').format();

  // Permissions
  const { data: canEdit } = useCan({ action: '7', resource: 'transactions' });
  const { data: canCreate } = useCan({ action: '32', resource: 'transactions' });
  const { data: canShowSI } = useCan({ action: '34', resource: 'SI' });
  const { data: profile } = useGetIdentity<Profile>();

  // Custom hooks
  const { Select: MerchantSelect, data: merchants } = useMerchant({ valueField: 'username' });
  const { Select: ProviderSelect, data: providers } = useProvider({ valueField: 'username' });
  const { Select: ChannelSelect, channels } = useChannel();
  const { Select: UserChannelSelect } = useUserChannelAccount();
  const { data: channelGroups } = useChannelGroup();

  const { Select: ThirdChannelSelect, data: thirdChannels } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    labelRender: record => `${record.thirdChannel}-${record.channel}`,
  });

  // Shared hooks
  const { Select: TransStatSelect, Status: tranStatus, getStatusText: getTranStatusText } =
    useTransactionStatus();
  const { Select: TranCallbackSelect, Status: tranCallbackStatus, getStatusText: getTranCallbackStatus } =
    useTransactionCallbackStatus();

  // Auto refetch
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  // System settings
  const { result: systemSettingsResult } = useList<SystemSetting>({
    resource: 'feature-toggles',
    pagination: { mode: 'off' },
  });

  // Refine useTable
  const {
    tableProps,
    searchFormProps,
    tableQuery: { data, refetch },
    filters,
  } = useTable<Transaction>({
    resource: 'transactions',
    syncWithLocation: true,
    filters: {
      initial: [
        { field: 'started_at', value: defaultStartAt, operator: 'eq' },
        { field: 'confirmed', value: 'created', operator: 'eq' },
      ],
    },
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });

  const meta = (data as any)?.meta as Meta;

  // Statistics custom query
  const { result: customResult, query: customQuery } = useCustom<Stat>({
    url: `${apiUrl}/transactions/statistics`,
    config: {
      filters: filters as CrudFilter[],
    },
    queryOptions: {
      queryKey: ['transactions-statistics', enableAuto, freq],
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
    method: 'get',
  });
  const stat = customResult?.data;
  const statisticsRefetch = customQuery.refetch;

  // Update Modal
  const { Modal: UpdateModal, show, modalProps } = useUpdateModal({
    formItems: [
      {
        label: t('fields.amount'),
        name: 'amount',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      { label: t('fields.note'), name: 'note', children: <Input /> },
      { name: 'realname', hidden: true },
      { name: 'type', hidden: true },
      { name: 'ipv4', hidden: true },
      { name: 'transaction_id', hidden: true },
      {
        name: 'delay_settle_minutes',
        children: (
          <Select
            options={[
              { label: '即时', value: 0 },
              { label: '5分钟', value: 5 },
              { label: '10分钟', value: 10 },
              { label: '15分钟', value: 15 },
            ]}
          />
        ),
      },
    ],
    onSuccess: () => refetch(),
  });

  // Create Transaction Modal
  const [createModalOpen, setCreateModalOpen] = useState(false);

  // Custom mutation
  const { mutateAsync: customMutate } = useCustomMutation();

  // Column dependencies
  const columnDeps: ColumnDependencies = {
    t,
    apiUrl,
    canEdit: canEdit?.can ?? false,
    canShowSI: canShowSI?.can ?? false,
    isPaufen,
    groupLabel,
    profile,
    meta: meta ?? { total: 0, banned_realnames: [], banned_ips: [] },
    tranStatus,
    tranCallbackStatus,
    getTranStatusText,
    getTranCallbackStatus,
    refetch,
    show,
    customMutate,
    Modal: UpdateModal,
  };

  const columns = useColumns(columnDeps);

  // Export handler
  const handleExport = () => {
    const url = `${apiUrl}/transaction-report?${queryString.stringify(
      generateFilter(filters as CrudFilter[])
    )}&token=${getToken()}`;
    window.open(url);
  };

  return (
    <>
      <Helmet>
        <title>{t('types.collection')}</title>
      </Helmet>
      <List
        title={t('types.collection')}
        headerButtons={() => (
          <>
            <Button
              disabled={!canCreate?.can}
              icon={<PlusOutlined />}
              onClick={() => setCreateModalOpen(true)}
            >
              {t('actions.createEmptyOrder')}
            </Button>
            <CreateButton icon={<PlayCircleOutlined />}>
              {t('buttons.testOrder')}
            </CreateButton>
            <Button icon={<ExportOutlined />} onClick={handleExport}>
              {t('buttons.export')}
            </Button>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={searchFormProps}
            loading={tableProps.loading as boolean}
            onSearch={() => statisticsRefetch()}
          >
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.date')}
                name="started_at"
                rules={[{ required: true }]}
                initialValue={dayjs().startOf('days')}
              >
                <CustomDatePicker
                  showTime
                  className="w-full"
                  onFastSelectorChange={(startAt, endAt) =>
                    searchFormProps.form?.setFieldsValue({
                      started_at: startAt,
                      ended_at: endAt,
                    })
                  }
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.date')} name="ended_at">
                <DatePicker showTime className="w-full" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.merchantOrderOrSystemOrder')}
                name="order_number_or_system_order_number"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.orderStatus')} name="status[]">
                <TransStatSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.providerNameTitle', { groupLabel })}
                name="provider_name_or_username[]"
              >
                <ProviderSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.merchantNameOrAccount')}
                name="merchant_name_or_username[]"
              >
                <MerchantSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.channel')} name="channel_code[]">
                <ChannelSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.orderAmount')}
                name="amount"
              >
                <Input placeholder={t('fields.amountRange')} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.collectionAccount')}
                name="account"
              >
                <Input />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.thirdPartyName')}
                name="thirdchannel_id[]"
              >
                <ThirdChannelSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.accountNumber')}
                name="provider_channel_account_hash_id[]"
              >
                <UserChannelSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.transferName')}
                name="real_name"
              >
                <Input />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.phone')} name="phone_account">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.callbackStatus')}
                name="notify_status[]"
              >
                <TranCallbackSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.memberIp')} name="client_ipv4">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={12}>
              <ListPageLayout.Filter.Item
                label={t('filters.classification')}
                name="confirmed"
                initialValue="created"
              >
                <Radio.Group>
                  <Radio value="created">{t('filters.byCreateTime')}</Radio>
                  <Radio value="confirmed">{t('filters.bySuccessTime')}</Radio>
                </Radio.Group>
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />

        <StatisticsCards meta={meta} stat={stat} t={t} />

        <Divider />

        <AutoRefetch />

        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>

      {/* Update Modal */}
      <UpdateModal {...modalProps} />

      {/* Create Transaction Modal */}
      <CreateTransactionModal
        open={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSuccess={refetch}
        apiUrl={apiUrl}
        t={t}
        groupLabel={groupLabel}
        merchants={merchants}
        providers={providers}
        channels={channels}
        channelGroups={channelGroups}
        thirdChannels={thirdChannels}
        MerchantSelect={MerchantSelect}
        ProviderSelect={ProviderSelect}
        useUserChannelAccount={useUserChannelAccount}
      />
    </>
  );
};

export default CollectionList;
