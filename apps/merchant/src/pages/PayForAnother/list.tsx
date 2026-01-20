/**
 * PayForAnother List Page - 重構後版本
 *
 * 使用 Refine useTable + ListPageLayout + shared hooks
 */
import { FC } from 'react';
import {
  Badge,
  Card,
  Col,
  DatePicker,
  Divider,
  Input,
  Radio,
  Row,
  Select,
  Statistic,
} from 'antd';
import type { BadgeProps, ColProps, TableColumnProps } from 'antd';
import {
  CreateButton,
  DateField,
  ExportButton,
  List,
  ListButton,
  useTable,
} from '@refinedev/antd';
import {
  ListPageLayout,
  useWithdrawStatus,
  useTransactionCallbackStatus,
  useSelector,
  Format,
  TransactionSubType,
} from '@morgan-ustd/shared';
import type { CrudFilter } from '@refinedev/core';
import { useGetLocale, useTranslate } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import dayjs from 'dayjs';
import { PlusSquareOutlined } from '@ant-design/icons';
import queryString from 'query-string';

import { Meta, Withdraw } from 'interfaces/withdraw';
import { Descendant } from 'interfaces/descendant';
import CustomDatePicker from 'components/customDatePicker';
import { getToken } from 'authProvider';
import { apiUrl } from 'index';
import { generateFilter } from 'dataProvider';

const PayForAnotherList: FC = () => {
  const defaultStartAt = dayjs().startOf('days').format();
  const translate = useTranslate();
  const locale = useGetLocale();
  const title = translate('withdraw.titles.main');

  const colProps: ColProps = {
    xs: 24,
    sm: 24,
    md: 6,
  };

  // Shared hooks
  const { Select: DescendantSelect } = useSelector<Descendant>({
    valueField: 'username',
    resource: 'descendants',
    labelField: 'username',
  });

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

  // Refine useTable
  const {
    tableProps,
    searchFormProps,
    tableQuery: { data },
    filters,
  } = useTable<Withdraw>({
    resource: 'withdraws',
    syncWithLocation: true,
    filters: {
      initial: [
        { field: 'started_at', value: defaultStartAt, operator: 'eq' },
        { field: 'confirmed', value: 'created', operator: 'eq' },
        { field: 'lang', value: locale(), operator: 'eq' },
      ],
    },
  });

  const meta = (data as any)?.meta as Meta;

  // Columns with responsive RWD
  const columns: TableColumnProps<Withdraw>[] = [
    {
      title: translate('collection.fields.systemTransactionNo'),
      dataIndex: 'system_order_number',
      responsive: ['xl', 'xxl'],
    },
    {
      title: translate('collection.fields.merchantTransactionNo'),
      dataIndex: 'order_number',
      fixed: 'left' as const,
      width: 150,
    },
    {
      title: translate('collection.fields.merchantNo'),
      dataIndex: ['merchant', 'username'],
      responsive: ['md', 'lg', 'xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.type'),
      dataIndex: 'subType',
      responsive: ['md', 'lg', 'xl', 'xxl'],
      render(value) {
        return value === 1
          ? translate('withdraw.values.withdraw')
          : translate('withdraw.values.payout');
      },
    },
    {
      title: translate('collection.fields.amount'),
      dataIndex: 'amount',
      responsive: ['sm', 'md', 'lg', 'xl', 'xxl'],
    },
    {
      title: translate('collection.fields.fee'),
      dataIndex: 'fee',
      responsive: ['lg', 'xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.withdrawStatus'),
      dataIndex: 'status',
      fixed: 'right' as const,
      width: 100,
      render(value) {
        let status: BadgeProps['status'];
        if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
          status = 'success';
        } else if (
          [
            WithdrawStatus.支付超时,
            WithdrawStatus.匹配超时,
            WithdrawStatus.失败,
          ].includes(value)
        ) {
          status = 'error';
        } else if (
          [
            WithdrawStatus.审核中,
            WithdrawStatus.匹配中,
            WithdrawStatus.等待付款,
            WithdrawStatus.三方处理中,
          ].includes(value)
        ) {
          status = 'processing';
        }
        return <Badge status={status} text={getWithdrawStatusText(value)} />;
      },
    },
    {
      title: translate('withdraw.fields.accountOwner'),
      dataIndex: 'bank_card_holder_name',
      responsive: ['sm', 'md', 'lg', 'xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.bankName'),
      dataIndex: 'bank_name',
      responsive: ['md', 'lg', 'xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.bankAccount'),
      dataIndex: 'bank_card_number',
      responsive: ['md', 'lg', 'xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.province'),
      dataIndex: 'bank_province',
      responsive: ['xl', 'xxl'],
    },
    {
      title: translate('withdraw.fields.city'),
      dataIndex: 'bank_city',
      responsive: ['xl', 'xxl'],
    },
    {
      title: translate('createAt'),
      dataIndex: 'created_at',
      responsive: ['md', 'lg', 'xl', 'xxl'],
      width: 160,
      render(value) {
        return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
      },
    },
    {
      title: translate('confirmAt'),
      dataIndex: 'confirmed_at',
      responsive: ['lg', 'xl', 'xxl'],
      width: 160,
      render(value) {
        return value ? (
          <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />
        ) : null;
      },
    },
    {
      title: translate('collection.fields.callbackStatus'),
      dataIndex: 'notify_status',
      responsive: ['lg', 'xl', 'xxl'],
      render(value) {
        let status: BadgeProps['status'];
        if ([tranCallbackStatus.成功].includes(value)) {
          status = 'default';
        } else if (
          [tranCallbackStatus.通知中, tranCallbackStatus.已通知].includes(value)
        ) {
          status = 'processing';
        } else if (tranCallbackStatus.未通知 === value) {
          status = 'default';
        } else if (tranCallbackStatus.失败) {
          status = 'error';
        }
        return <Badge status={status} text={getTranCallbackStatus(value)} />;
      },
    },
    {
      title: translate('withdraw.fields.callbackTime'),
      dataIndex: 'notified_at',
      responsive: ['xl', 'xxl'],
      render(value) {
        return value ? <DateField value={value} format={Format} /> : '';
      },
    },
  ];

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
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={() => (
          <>
            <CreateButton resource="withdraws">
              {translate('withdraw.buttons.createPayment')}
            </CreateButton>
            <ListButton icon={<PlusSquareOutlined />} resource="pay-for-another">
              {translate('withdraw.buttons.createWithdraw')}
            </ListButton>
            <ListButton resource="bank-cards">
              {translate('withdraw.buttons.banks')}
            </ListButton>
            <ExportButton onClick={handleExport}>
              {translate('export')}
            </ExportButton>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={searchFormProps}
            loading={tableProps.loading as boolean}
          >
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('datePicker.startDate')}
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
              <ListPageLayout.Filter.Item
                label={translate('datePicker.endDate')}
                name="ended_at"
              >
                <DatePicker showTime className="w-full" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('collection.fields.transactionNo')}
                name="order_number_or_system_order_number"
              >
                <Input />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('collection.fields.merchantNo')}
                name="descendant_merchent_username_or_name"
              >
                <DescendantSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('withdraw.fields.bankCardKeyword')}
                name="bank_card_q"
              >
                <Input />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('withdraw.fields.withdrawStatus')}
                name="status[]"
              >
                <WithdrawStatusSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('withdraw.fields.type')}
                name="sub_type[]"
              >
                <Select
                  mode="multiple"
                  options={[
                    {
                      label: translate('withdraw.values.withdraw'),
                      value: TransactionSubType.SUB_TYPE_WITHDRAW,
                    },
                    {
                      label: translate('withdraw.values.payout'),
                      value: TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW,
                    },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={translate('collection.fields.callbackStatus')}
                name="notify_status[]"
              >
                <TranCallbackSelect mode="multiple" />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={12}>
              <ListPageLayout.Filter.Item
                label={translate('collection.fields.category')}
                name="confirmed"
                initialValue="created"
              >
                <Radio.Group>
                  <Radio value="created">
                    {translate('collection.fields.queryOrderWithCreateAt')}
                  </Radio>
                  <Radio value="confirmed">
                    {translate('collection.fields.queryOrderWithSucceedAt')}
                  </Radio>
                </Radio.Group>
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />

        {/* Statistics Cards */}
        <Row gutter={16}>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.total}
                title={translate('withdraw.fields.totalNumberOfWithdraw')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.total_amount}
                title={translate('withdraw.fields.totalAmountOfWithdraw')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.total_fee}
                title={translate('withdraw.fields.totalFeeOfWithdraw')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.balance}
                title={translate('withdraw.fields.balanceOfWithdraw')}
              />
            </Card>
          </Col>
        </Row>

        <Divider />

        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>
    </>
  );
};

export default PayForAnotherList;
