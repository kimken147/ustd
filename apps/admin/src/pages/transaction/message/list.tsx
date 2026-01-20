import { List, useTable } from '@refinedev/antd';
import { Col, DatePicker, Divider, Input } from 'antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useAutoRefetch from 'hooks/useAutoRefetch';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns } from './columns';
import type { MessageRecord } from './columns/types';

const TransactionMessageList: FC = () => {
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const { tableProps, searchFormProps } = useTable<MessageRecord>({
    resource: 'notifications',
    syncWithLocation: true,
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });

  const columns = useColumns();

  return (
    <List>
      <Helmet>
        <title>短信</title>
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
            <ListPageLayout.Filter.Item label="结束日期" name="ended_at" trigger="onSelect">
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
          <Col xs={24} md={6}>
            <ListPageLayout.Filter.Item label="短信账号" name="mobile">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={6}>
            <ListPageLayout.Filter.Item label="内容" name="content">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <AutoRefetch />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
    </List>
  );
};

export default TransactionMessageList;
