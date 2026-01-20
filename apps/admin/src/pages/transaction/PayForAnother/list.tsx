/**
 * PayForAnother List Page - 重構後版本
 *
 * 使用 ListPageLayout + 抽取的 FilterForm 和 useColumns
 * 將原本 1348 行重構為更易維護的結構
 */
import { List, ListButton } from '@refinedev/antd';
import {
  Button,
  Card,
  Col,
  ColProps,
  Divider,
  Input,
  Row,
  Space,
  Statistic,
  Modal as AntdModal,
  Select,
  SelectProps,
  Switch,
  Table,
  Grid,
} from 'antd';
import { useTable } from '@refinedev/antd';
import {
  ListPageLayout,
  useWithdrawStatus,
  useTransactionCallbackStatus,
  useUpdateModal,
  useSelector,
  WithdrawMeta as Meta,
  Withdraw,
} from '@morgan-ustd/shared';
import { FC, useEffect, useState } from 'react';
import { Helmet } from 'react-helmet';
import useMerchant from 'hooks/useMerchant';
import useChannel from 'hooks/useChannel';
import dayjs from 'dayjs';
import { ExportOutlined } from '@ant-design/icons';
import {
  CrudFilter,
  useApiUrl,
  useCan,
  useCustomMutation,
  useGetIdentity,
} from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import useAutoRefetch from 'hooks/useAutoRefetch';
import { useNavigate } from 'react-router';
import queryString from 'query-string';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import { MerchantThirdChannel } from 'interfaces/merchantThirdChannel';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { isEqual } from 'lodash';
import { Provider } from 'interfaces/provider';
import Enviroment from 'lib/env';
import NoticeAudio from 'assets/notice.mp3';
import { useAudioPermission } from 'hooks/useAudioPermission';
import AudioPermissionAlert from 'components/AudioPermissionAlert';
import { useTranslation } from 'react-i18next';
import { TextField } from '@refinedev/antd';

// Page components
import { FilterForm } from './components/FilterForm';
import { useColumns } from './components/columns';

const PayForAnotherList: FC = () => {
  const { t } = useTranslation('transaction');
  const isPaufen = Enviroment.isPaufen;
  const navigate = useNavigate();
  const apiUrl = useApiUrl();
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('days').format();
  const colProps: ColProps = { xs: 24, sm: 24, md: 6 };
  const breakpoint = Grid.useBreakpoint();
  const isSmallScreen = breakpoint.xs || breakpoint.sm || breakpoint.md;

  const { data: canEdit } = useCan({
    action: '12',
    resource: 'withdraws',
  });

  const [selectedMerchantId, setSelectMerchantId] = useState(0);
  const { Select: MerchantSelect } = useMerchant({ valueField: 'username' });
  const { Select: ChannelSelect } = useChannel();
  const { Select: ThirdChannelSelect } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    labelRender(record) {
      return `${record.thirdChannel}-${record.channel}`;
    },
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

  let currentMerchantThirdChannelSelect: SelectProps['options'] = [];
  if (merchantThirdChannel?.length) {
    const thirdChannelsList = merchantThirdChannel?.find(
      m => m.id === selectedMerchantId
    )?.thirdChannelsList;
    if (thirdChannelsList) {
      currentMerchantThirdChannelSelect = Object.values(thirdChannelsList).map(
        t => ({ label: t.thirdChannel, value: t.thirdchannel_id })
      );
    }
  }

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

  const {
    Modal,
    show: showUpdateModal,
    modalProps,
  } = useUpdateModal({
    formItems: [
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input.TextArea />,
      },
      { name: 'realname', hidden: true },
      { name: 'type', hidden: true },
      { name: 'ipv4', hidden: true },
      { name: 'transaction_id', hidden: true },
      {
        name: 'to_thirdchannel_id',
        children: <Select options={currentMerchantThirdChannelSelect} />,
      },
      {
        name: 'withdrawType',
        label: t('withdraw.type'),
        children: (
          <Select
            options={[
              { label: t('types.manualAgency'), value: 4 },
              { label: t('types.paufenAgency'), value: 2 },
            ]}
          />
        ),
      },
      {
        name: 'to_id',
        label: t('fields.assignProvider'),
        children: (
          <Select
            {...providerSelectProps}
            options={[
              { label: t('placeholders.notAssign'), value: null },
              ...(providerSelectProps.options ?? []),
            ]}
          />
        ),
      },
    ],
    transferFormValues(record) {
      if (record.withdrawType) {
        return { ...record, type: record.withdrawType };
      }
      return record;
    },
  });

  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  // Use Refine's useTable
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

  const meta = (data as any)?.meta as Meta || { banned_realnames: [] };
  const withdrawData = data?.data;
  const pagination = tableProps.pagination as { current?: number };

  const { mutateAsync } = useCustomMutation();

  // Use extracted columns
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

  // Audio notification state
  const [previouData, setPrevData] = useState<{
    withdraws?: Withdraw[];
    page?: number;
    filters?: CrudFilter[];
  }>({ page: 1, filters });

  const {
    showPermissionAlert,
    grantPermission,
    dismissPermissionAlert,
    playAudio,
  } = useAudioPermission(NoticeAudio);

  const [enableNotice, setEnableNotice] = useState(true);
  useEffect(() => {
    if (enableNotice) {
      if (
        previouData &&
        previouData.page === pagination?.current &&
        withdrawData?.[0]?.id &&
        previouData.withdraws?.[0]?.id !== withdrawData?.[0]?.id &&
        isEqual(previouData.filters, filters)
      ) {
        playAudio();
        setPrevData({ ...previouData, withdraws: withdrawData });
      }
      if (
        !isEqual(previouData.filters, filters) ||
        previouData?.page !== pagination?.current
      ) {
        setPrevData({
          withdraws: withdrawData,
          page: pagination?.current,
          filters: filters,
        });
      }
    }
  }, [withdrawData, pagination, previouData, enableNotice, filters, playAudio]);

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
            <Button
              icon={<ExportOutlined />}
              onClick={async () => {
                const url = `${apiUrl}/withdraw-report?${queryString.stringify(
                  generateFilter(filters as CrudFilter[])
                )}&token=${getToken()}`;
                window.open(url);
              }}
            >
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
        <Row gutter={[16, 16]}>
          <Col {...colProps}>
            <Card className="border-[#ff4d4f] border-[2.5px]">
              <Statistic
                value={meta?.total}
                title={t('statistics.paymentCount')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#3f7cac] border-[2.5px]">
              <Statistic
                value={meta?.total_amount}
                title={t('statistics.paymentAmount')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={meta?.total_profit}
                title={t('statistics.paymentProfit')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={meta?.third_channel_fee}
                title={t('statistics.thirdPartyFee')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <Space>
          <AutoRefetch />
          <Space className="px-4 mb-4">
            <TextField value={t('switches.soundAlert')} />
            <Switch
              checked={enableNotice}
              onChange={checked => setEnableNotice(checked)}
            />
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
