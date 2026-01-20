import { FC, useState } from 'react';
import { Col, Divider, Input, InputNumber, Modal, Select } from 'antd';
import { CreateButton, List, useTable } from '@refinedev/antd';
import {
  useApiUrl,
  useCan,
  useDelete,
  useUpdate,
} from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import {
  ListPageLayout,
  ProviderUserChannel as UserChannel,
  Meta,
  SelectOption,
  UserChannelStatus,
  UserChannelType,
  Resource,
} from '@morgan-ustd/shared';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useBank from 'hooks/useBank';
import useChannelAmounts from 'hooks/useChannelAmounts';
import useChannelStatus from 'hooks/useChannelStatus';
import useProvider from 'hooks/useProvider';
import useRegion from 'hooks/useRegion';
import useSystemSetting from 'hooks/useSystemSetting';
import useUpdateModal from 'hooks/useUpdateModal';
import useUserChannelAccount from 'hooks/useUserChannelAccount';
import useUserChannelStatus from 'hooks/useUserChannelStatus';
import Enviroment from 'lib/env';
import { useColumns, type ColumnDependencies } from './columns';
import { StatisticsCard } from './StatisticsCard';
import { BatchOperationsBar } from './BatchOperationsBar';

const UserChannelAccountList: FC = () => {
  const { t } = useTranslation('userChannel');
  const { getChannelStatusText, getChannelTypeText } = useChannelStatus();
  const isPaufen = Enviroment.isPaufen;
  const name = isPaufen ? t('fields.providerName') : t('fields.groupName');
  const apiUrl = useApiUrl();
  const region = useRegion();
  const { Select: UserChannelAccountStatusSelect } = useUserChannelStatus();

  const { data: canEdit } = useCan({ action: '5', resource: 'user-channel-accounts' });
  const { data: canDelete } = useCan({ action: '6', resource: 'user-channel-accounts' });

  const { data: providers, Select: ProviderSelect } = useProvider();
  const { data: banks } = useBank();
  const { data: channelAmounts } = useChannelAmounts();
  const { data: userChannelAccounts } = useUserChannelAccount();
  const { data: systemSetting } = useSystemSetting();

  const dayEnable = systemSetting?.find(x => x.id === 35)?.enabled ?? false;
  const monthEnable = systemSetting?.find(x => x.id === 45)?.enabled ?? false;

  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();
  const [selectedKeys, setSelectedKeys] = useState<React.Key[]>([]);

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: tableData, refetch },
  } = useTable<UserChannel>({
    resource: Resource.userChannelAccounts,
    syncWithLocation: true,
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });

  const meta = (tableData as any)?.meta as Meta | undefined;
  const data = tableData?.data;

  const { show: showUpdateModal, modalProps } = useUpdateModal({
    formItems: [
      { label: name, name: 'provider_id', children: <ProviderSelect /> },
      { label: t('fields.balance'), name: 'balance', children: <InputNumber className="w-full" /> },
      { label: t('fields.balanceLimit'), name: 'balance_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.mpin'), name: 'mpin', children: <Input /> },
      { label: t('fields.dailyLimitReceive'), name: 'daily_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.dailyLimitPayout'), name: 'withdraw_daily_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.monthlyLimitReceive'), name: 'monthly_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.monthlyLimitPayout'), name: 'withdraw_monthly_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.singleMinLimit'), name: 'single_min_limit', children: <InputNumber className="w-full" /> },
      { label: t('fields.singleMaxLimit'), name: 'single_max_limit', children: <InputNumber className="w-full" /> },
      { name: 'allow_unlimited' },
      { label: t('fields.note'), name: 'note', children: <Input /> },
      {
        label: t('fields.status'),
        name: 'status',
        children: (
          <Select
            options={[
              { value: UserChannelStatus.强制下线, label: getChannelStatusText(UserChannelStatus.强制下线) },
              { value: UserChannelStatus.下线, label: getChannelStatusText(UserChannelStatus.下线) },
              { value: UserChannelStatus.上线, label: getChannelStatusText(UserChannelStatus.上线) },
            ]}
          />
        ),
        rules: [{ required: true }],
      },
      {
        label: t('fields.type'),
        name: 'type',
        children: <UserChannelAccountStatusSelect />,
        rules: [{ required: true }],
      },
      { label: t('fields.newPassword'), name: 'newPassword', children: <Input /> },
      { label: t('fields.newEmail'), name: 'newEmail', children: <Input type="email" /> },
    ],
  });

  const { mutateAsync: mutateUpdating } = useUpdate();
  const { mutateAsync: mutateDeleting } = useDelete();

  const mutateUserChannel = ({
    record,
    values,
    title = t('confirmation.modify'),
    method = 'put',
  }: {
    record: UserChannel;
    values: Record<string, any>;
    title?: string;
    method?: 'put' | 'delete';
  }) => {
    Modal.confirm({
      title,
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        if (method === 'put') {
          mutateUpdating({
            resource: Resource.userChannelAccounts,
            id: record.id,
            values: { ...values, id: record.id },
            successNotification: { message: t('messages.updateSuccess'), type: 'success' },
          });
        } else {
          mutateDeleting({
            resource: Resource.userChannelAccounts,
            id: record.id,
            successNotification: { message: t('messages.deleteSuccess'), type: 'success' },
          });
        }
      },
    });
  };

  const columnDeps: ColumnDependencies = {
    t,
    name,
    region: region ?? '',
    canEdit: canEdit?.can ?? false,
    canDelete: canDelete?.can ?? false,
    isPaufen,
    dayEnable,
    monthEnable,
    getChannelStatusText,
    getChannelTypeText,
    showUpdateModal,
    mutateUserChannel,
    mutateDeleting: (opts) => mutateDeleting(opts),
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('titles.pageTitle')}</title>
      </Helmet>
      <List headerButtons={() => <CreateButton>{t('actions.create')}</CreateButton>}>
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={name} name="name_or_username">
                <Select
                  options={providers?.map<SelectOption>(provider => ({
                    label: provider.name,
                    value: provider.name,
                    key: String(provider.id),
                  }))}
                  optionFilterProp="label"
                  showSearch
                  allowClear
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.channel')} name="channel_code[]">
                <Select
                  allowClear
                  mode="multiple"
                  options={channelAmounts?.map(channel => ({
                    value: channel.channel_code,
                    label: channel.name,
                    key: channel.id,
                  }))}
                  optionFilterProp="label"
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.status')} name="status[]">
                <Select
                  allowClear
                  mode="multiple"
                  options={[
                    { value: UserChannelStatus.强制下线, label: getChannelStatusText(UserChannelStatus.强制下线) },
                    { value: UserChannelStatus.下线, label: getChannelStatusText(UserChannelStatus.下线) },
                    { value: UserChannelStatus.上线, label: getChannelStatusText(UserChannelStatus.上线) },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.accountName')} name="account_name">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.bankName')} name="bank[]">
                <Select
                  optionFilterProp="label"
                  mode="multiple"
                  allowClear
                  options={banks?.map(bank => ({
                    value: bank.id,
                    label: bank.name,
                    key: bank.id,
                  }))}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.account')} name="name[]">
                <Select
                  options={userChannelAccounts?.map<SelectOption>(acc => ({
                    label: `${acc.account}(${acc.name})`,
                    value: acc.name,
                  }))}
                  showSearch
                  optionFilterProp="label"
                  mode="multiple"
                  allowClear
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.note')} name="note">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <StatisticsCard data={data} meta={meta} selectedKeys={selectedKeys} t={t} />
        <Divider />
        <AutoRefetch />
        <BatchOperationsBar
          selectedKeys={selectedKeys}
          setSelectedKeys={setSelectedKeys}
          canEdit={canEdit?.can ?? false}
          apiUrl={apiUrl}
          showUpdateModal={showUpdateModal}
          refetch={refetch}
          t={t}
        />
        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          rowSelection={
            canEdit?.can
              ? {
                  selectedRowKeys: selectedKeys,
                  onChange: keys => setSelectedKeys(keys),
                  preserveSelectedRowKeys: true,
                }
              : undefined
          }
        />
      </List>
      <Modal {...modalProps} />
    </>
  );
};

export default UserChannelAccountList;
