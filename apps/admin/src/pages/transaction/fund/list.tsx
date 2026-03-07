import { CreateButton, List, useTable } from '@refinedev/antd';
import { useCan, useGetIdentity } from '@refinedev/core';
import { Col, DatePicker, Divider, Input, Modal as AntdModal, Radio } from 'antd';
import { ListPageLayout, formValuesToCrudFilters } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useTransactionStatus from 'hooks/useTransactionStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import useWithdrawStatus from 'hooks/useWithdrawStatus';
import { apiUrl } from 'index';
import { InternalTransfer } from 'interfaces/internalTransfer';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const FundList: FC = () => {
  const { t } = useTranslation('transaction');
  const defaultStartAt = dayjs().startOf('day').format('YYYY-MM-DDTHH:mm:ss');
  const { data: profile } = useGetIdentity<Profile>();
  const { Select: TranStatusSelect } = useTransactionStatus();
  const { Status: WithdrawStatus, getStatusText: getWithdrawStatusText } = useWithdrawStatus();
  const { data: canEdit } = useCan({ action: '35', resource: 'internal-transfers' });
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const {
    modalProps,
    show: showUpdateModal,
    Modal,
  } = useUpdateModal({
    formItems: [
      { label: t('fields.note'), name: 'note', children: <Input.TextArea /> },
      { name: 'transaction_id', hidden: true },
    ],
  });

  const { tableProps, searchFormProps } = useTable<InternalTransfer>({
    onSearch: formValuesToCrudFilters,
    resource: 'internal-transfers',
    syncWithLocation: true,
    filters: {
      initial: [{ field: 'started_at', value: defaultStartAt, operator: 'eq' }],
    },
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });

  const columnDeps: ColumnDependencies = {
    t,
    profile,
    WithdrawStatus,
    getWithdrawStatusText,
    showUpdateModal,
    Modal,
    apiUrl,
    canEdit: canEdit?.can ?? false,
  };

  const columns = useColumns(columnDeps);

  return (
    <List headerButtons={() => <CreateButton>{t('fund.create')}</CreateButton>}>
      <Helmet>
        <title>{t('fund.title')}</title>
      </Helmet>

      <ListPageLayout>
        <ListPageLayout.Filter
          formProps={{ ...searchFormProps, initialValues: { started_at: dayjs().startOf('days') } }}
          defaultValues={{ started_at: dayjs().startOf('day') }}
        >
          <Col xs={24} md={6}>
            <ListPageLayout.Filter.Item
              label={t('fields.startDate')}
              name="started_at"
              rules={[{ required: true }]}
            >
              <CustomDatePicker
                showTime
                className="w-full"
                onFastSelectorChange={(startAt, endAt) =>
                  searchFormProps.form?.setFieldsValue({ started_at: startAt, ended_at: endAt })
                }
              />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={6}>
            <ListPageLayout.Filter.Item label={t('fields.endDate')} name="ended_at">
              <DatePicker
                showTime
                className="w-full"
                disabledDate={current => {
                  const startAt = searchFormProps.form?.getFieldValue('started_at') as Dayjs;
                  return current && startAt && (current > startAt.add(1, 'month') || current < startAt);
                }}
              />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label={t('fields.status')} name="status[]">
              <TranStatusSelect allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label={t('fields.collectionAccount')} name="bank_card_number">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label={t('fields.paymentAccountNumber')} name="account">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label={t('fields.systemOrderNumber')} name="system_order_number">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label={t('filters.category')} name="confirmed">
              <Radio.Group>
                <Radio value="created">{t('filters.byCreateTime')}</Radio>
                <Radio value="confirmed">{t('filters.bySuccessTime')}</Radio>
              </Radio.Group>
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <AutoRefetch />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />

      <AntdModal {...modalProps} />
    </List>
  );
};

export default FundList;
