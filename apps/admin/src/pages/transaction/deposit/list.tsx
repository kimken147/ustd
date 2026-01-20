import { FC, useState } from 'react';
import { Col, DatePicker, Divider, Input, Modal, Select } from 'antd';
import { List, ListButton, useTable } from '@refinedev/antd';
import { useApiUrl, useGetIdentity, useList } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import dayjs, { Dayjs } from 'dayjs';
import { ListPageLayout, Merchant } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useSelector from 'hooks/useSelector';
import useTransactionStatus from 'hooks/useTransactionStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import type { Deposit } from 'interfaces/deposit';
import type { Provider } from 'interfaces/provider';
import type { SystemSetting } from 'interfaces/systemSetting';
import { useColumns, type ColumnDependencies } from './columns';
import { SuccessModal } from './SuccessModal';

const DepositList: FC = () => {
  const { t } = useTranslation('transaction');
  const title = t('titles.providerDeposit');
  const apiUrl = useApiUrl();
  const defaultStartAt = dayjs().startOf('days').format();
  const { data: profile } = useGetIdentity<Profile>();
  const [current, setCurrent] = useState<Deposit>();
  const [successModalOpen, setSuccessModalOpen] = useState(false);

  const { getStatusText, Status, selectProps: transactionStatusSelectProps } =
    useTransactionStatus();

  const { Select: ProviderSelect } = useSelector<Provider>({
    resource: 'providers',
    valueField: 'name',
  });

  const { Select: MerchantSelect } = useSelector<Merchant>({
    resource: 'merchants',
    valueField: 'name',
    labelRender(record) {
      return `${record.username}(${record.name})`;
    },
  });

  const { result: systemSettingsResult } = useList<SystemSetting>({
    resource: 'feature-toggles',
    pagination: { mode: 'off' },
  });

  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<Deposit>({
    resource: 'deposits',
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

  const {
    Modal: UpdateModal,
    show,
    modalProps: updateModalProps,
  } = useUpdateModal({
    resource: 'deposits',
    formItems: [
      {
        label: t('fields.delaySettleMinutes'),
        name: 'delay_settle_minutes',
        children: (
          <Select
            options={[
              { label: t('buttons.instant'), value: 0 },
              { label: t('buttons.5min'), value: 5 },
              { label: t('buttons.10min'), value: 10 },
              { label: t('buttons.15min'), value: 15 },
            ]}
          />
        ),
      },
      { label: '' },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input.TextArea />,
        rules: [{ required: true }],
      },
    ],
  });

  const columnDeps: ColumnDependencies = {
    t,
    apiUrl,
    profile,
    Status,
    getStatusText,
    show,
    showSuccessModal: () => setSuccessModalOpen(true),
    setCurrent,
    UpdateModal,
    refetch,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={
          <>
            {systemSettingsResult?.data.find(item => item.id === 20)?.enabled && (
              <ListButton resource="matching-deposit-rewards">
                {t('buttons.quickChargeReward')}
              </ListButton>
            )}
            <ListButton resource="system-bank-cards">
              {t('buttons.generalDepositBankCards')}
            </ListButton>
          </>
        }
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={searchFormProps}
            loading={tableProps.loading as boolean}
          >
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.startDate')}
                name="started_at"
                trigger="onSelect"
                initialValue={dayjs().startOf('days')}
              >
                <CustomDatePicker
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
              <ListPageLayout.Filter.Item label={t('fields.endDate')} name="ended_at">
                <DatePicker
                  className="w-full"
                  disabledDate={currentDate => {
                    const startAt = searchFormProps.form?.getFieldValue('started_at') as Dayjs;
                    return (
                      currentDate &&
                      (currentDate > startAt?.add(1, 'month') || currentDate < startAt)
                    );
                  }}
                />
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
                <Select {...transactionStatusSelectProps} mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.lockedBy')}
                name="operator_name_or_username"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.providerDepositType')}
                name="type"
              >
                <Select
                  allowClear
                  options={[
                    { label: t('types.all'), value: null },
                    { label: t('types.paufenDeposit'), value: 2 },
                    { label: t('types.generalDeposit'), value: 3 },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('filters.providerNameOrAccount')}
                name="provider_name_or_username[]"
              >
                <ProviderSelect allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.merchantNameOrAccount')}
                name="merchant_name_or_username"
              >
                <MerchantSelect allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('fields.bankCardKeyword')}
                name="bank_card_q"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.orderAmount')} name="amount">
                <Input placeholder={t('fields.amountRange')} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <AutoRefetch />
        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>

      <Modal {...updateModalProps} />
      <SuccessModal
        open={successModalOpen}
        current={current}
        onClose={() => setSuccessModalOpen(false)}
        t={t}
      />
    </>
  );
};

export default DepositList;
