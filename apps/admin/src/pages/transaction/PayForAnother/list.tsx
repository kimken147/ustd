/**
 * PayForAnother List Page - 重構後版本
 *
 * 使用 ListPageLayout + 抽取的元件和 hooks
 */
import { FC, useState } from 'react';
import { List, ListButton, TextField } from '@refinedev/antd';
import { useTable } from '@refinedev/antd';
import {
  Button,
  Divider,
  Space,
  Modal as AntdModal,
  SelectProps,
  Switch,
} from 'antd';
import {
  ListPageLayout,
  useWithdrawStatus,
  useTransactionCallbackStatus,
  useSelector,
  WithdrawMeta as Meta,
  Withdraw,
} from '@morgan-ustd/shared';
import { Helmet } from 'react-helmet';
import {
  CrudFilter,
  useApiUrl,
  useCan,
  useCustomMutation,
  useGetIdentity,
} from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import { useNavigate } from 'react-router';
import { ExportOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import queryString from 'query-string';
import { useTranslation } from 'react-i18next';

// Local imports
import useMerchant from 'hooks/useMerchant';
import useChannel from 'hooks/useChannel';
import useAutoRefetch from 'hooks/useAutoRefetch';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import { MerchantThirdChannel } from 'interfaces/merchantThirdChannel';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { Provider } from 'interfaces/provider';
import Enviroment from 'lib/env';
import NoticeAudio from 'assets/notice.mp3';
import AudioPermissionAlert from 'components/AudioPermissionAlert';

// Page components and hooks
import { FilterForm, useColumns, StatisticsCards } from './components';
import { useNewDataNotification, useUpdateModalConfig } from './hooks';

const PayForAnotherList: FC = () => {
  const { t } = useTranslation('transaction');
  const isPaufen = Enviroment.isPaufen;
  const navigate = useNavigate();
  const apiUrl = useApiUrl();
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('days').format();

  const { data: canEdit } = useCan({ action: '12', resource: 'withdraws' });

  // Selector hooks
  const [selectedMerchantId, setSelectMerchantId] = useState(0);
  const { Select: MerchantSelect } = useMerchant({ valueField: 'username' });
  const { Select: ChannelSelect } = useChannel();
  const { Select: ThirdChannelSelect } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    labelRender: record => `${record.thirdChannel}-${record.channel}`,
  });
  const { selectProps: providerSelectProps } = useSelector<Provider>({
    resource: 'users',
    filters: [{ field: 'role', value: 2, operator: 'eq' }],
  });
  const { data: merchantThirdChannel } = useSelector<MerchantThirdChannel>({
    resource: 'merchant-third-channel',
    filters: [
      { field: 'status', value: 1, operator: 'eq' },
      { field: 'merchant_id', value: selectedMerchantId, operator: 'eq' },
    ],
  });

  // Compute merchant third channel options
  let currentMerchantThirdChannelSelect: SelectProps['options'] = [];
  if (merchantThirdChannel?.length) {
    const thirdChannelsList = merchantThirdChannel?.find(
      m => m.id === selectedMerchantId
    )?.thirdChannelsList;
    if (thirdChannelsList) {
      currentMerchantThirdChannelSelect = Object.values(thirdChannelsList).map(
        item => ({ label: item.thirdChannel, value: item.thirdchannel_id })
      );
    }
  }

  // Status hooks
  const {
    Select: WithdrawStatusSelect,
    getStatusText: getWithdrawStatusText,
    Status: WithdrawStatus,
  } = useWithdrawStatus();
  const {
    Select: TranCallbackSelect,
    Status: tranCallbackStatus,
    getStatusText: getTranCallbackStatus,
  } = useTransactionCallbackStatus();

  // Modal hook
  const { Modal, show: showUpdateModal, modalProps } = useUpdateModalConfig({
    providerSelectProps,
    currentMerchantThirdChannelSelect,
  });

  // Auto refresh
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  // Table hook
  const {
    tableProps,
    searchFormProps,
    tableQuery: { data, refetch, isFetching },
    filters,
  } = useTable<Withdraw>({
    resource: 'withdraws',
    syncWithLocation: true,
    filters: {
      initial: [
        { field: 'started_at', value: defaultStartAt, operator: 'eq' },
        { field: 'confirmed', value: 'created', operator: 'eq' },
      ],
    },
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
      refetchIntervalInBackground: true,
    },
  });

  const meta = ((data as any)?.meta as Meta) || { banned_realnames: [] };
  const withdrawData = data?.data;
  const pagination = tableProps.pagination as { current?: number };
  const { mutateAsync } = useCustomMutation();

  // Columns
  const columns = useColumns({
    canEdit: canEdit?.can ?? false,
    profile,
    apiUrl,
    navigate,
    showUpdateModal,
    modalConfirm: Modal.confirm,
    mutateAsync,
    refetch,
    getWithdrawStatusText,
    getTranCallbackStatus,
    WithdrawStatus,
    tranCallbackStatus,
    meta,
    providerSelectProps,
    currentMerchantThirdChannelSelect,
    setSelectMerchantId,
    axiosInstance,
  });

  // Audio notification
  const {
    enableNotice,
    setEnableNotice,
    showPermissionAlert,
    grantPermission,
    dismissPermissionAlert,
  } = useNewDataNotification({
    data: withdrawData,
    currentPage: pagination?.current,
    filters: filters as CrudFilter[],
    audioSrc: NoticeAudio,
  });

  // Export handler
  const handleExport = () => {
    const url = `${apiUrl}/withdraw-report?${queryString.stringify(
      generateFilter(filters as CrudFilter[])
    )}&token=${getToken()}`;
    window.open(url);
  };

  return (
    <>
      <Helmet>
        <title>{t('types.payment')}</title>
      </Helmet>
      <List
        title={t('types.payment')}
        headerButtons={() => (
          <>
            <ListButton resource="user-bank-cards">
              {isPaufen
                ? t('withdraw.merchantProviderBankList')
                : t('withdraw.merchantBankList')}
            </ListButton>
            <Button icon={<ExportOutlined />} onClick={handleExport}>
              {t('buttons.export')}
            </Button>
          </>
        )}
      >
        <ListPageLayout>
          <FilterForm
            formProps={searchFormProps}
            form={searchFormProps.form!}
            MerchantSelect={MerchantSelect}
            ChannelSelect={ChannelSelect}
            ThirdChannelSelect={ThirdChannelSelect}
            WithdrawStatusSelect={WithdrawStatusSelect}
            TranCallbackSelect={TranCallbackSelect}
            loading={isFetching}
          />
        </ListPageLayout>

        <Divider />
        <StatisticsCards meta={meta} />
        <Divider />

        <Space>
          <AutoRefetch />
          <Space className="px-4 mb-4">
            <TextField value={t('switches.soundAlert')} />
            <Switch checked={enableNotice} onChange={setEnableNotice} />
          </Space>
        </Space>

        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          rowKey="id"
          size="small"
        />
      </List>

      <AntdModal {...modalProps} />
      {showPermissionAlert && (
        <AudioPermissionAlert
          onConfirm={grantPermission}
          onDismiss={dismissPermissionAlert}
        />
      )}
    </>
  );
};

export default PayForAnotherList;
