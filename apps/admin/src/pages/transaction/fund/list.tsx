import { CreateButton, List, useTable } from '@refinedev/antd';
import { useGetIdentity } from '@refinedev/core';
import { Col, DatePicker, Divider, Input, Modal as AntdModal } from 'antd';
import { ListPageLayout } from '@morgan-ustd/shared';
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
  const defaultStartAt = dayjs().startOf('days').format();
  const { data: profile } = useGetIdentity<Profile>();
  const { Select: TranStatusSelect } = useTransactionStatus();
  const { Status: WithdrawStatus, getStatusText: getWithdrawStatusText } = useWithdrawStatus();
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const {
    modalProps,
    show: showUpdateModal,
    Modal,
  } = useUpdateModal({
    formItems: [
      { label: '备注', name: 'note', children: <Input.TextArea /> },
      { name: 'transaction_id', hidden: true },
    ],
  });

  const { tableProps, searchFormProps } = useTable<InternalTransfer>({
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
  };

  const columns = useColumns(columnDeps);

  return (
    <List headerButtons={() => <CreateButton>建立转账</CreateButton>}>
      <Helmet>
        <title>资金管理</title>
      </Helmet>

      <ListPageLayout>
        <ListPageLayout.Filter
          formProps={{ ...searchFormProps, initialValues: { started_at: dayjs().startOf('days') } }}
        >
          <Col xs={24} md={6}>
            <ListPageLayout.Filter.Item
              label="开始日期"
              name="started_at"
              trigger="onSelect"
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
            <ListPageLayout.Filter.Item label="结束日期" name="ended_at">
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
            <ListPageLayout.Filter.Item label="状态" name="status[]">
              <TranStatusSelect allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label="收款账号" name="bank_card_number">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={4}>
            <ListPageLayout.Filter.Item label="付款账号" name="account">
              <Input allowClear />
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
