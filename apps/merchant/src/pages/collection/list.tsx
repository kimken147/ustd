import { Card, Col, Divider, Form, Row } from 'antd';
import type { ColProps } from 'antd';
import { ExportButton, List, useTable } from '@refinedev/antd';
import dayjs from 'dayjs';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTransactionStatus, useTransactionCallbackStatus, ListPageLayout } from '@morgan-ustd/shared';
import type { Meta, Transaction } from 'interfaces/transaction';
import numeral from 'numeral';
import { useApiUrl, useGetLocale, useTranslate } from '@refinedev/core';
import queryString from 'query-string';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import { useColumns, type ColumnDependencies } from './columns';
import FilterForm from './FilterForm';

const CollectionList: FC = () => {
  const t = useTranslate();
  const locale = useGetLocale();
  const title = t('collection.titles.list');
  const apiUrl = useApiUrl();
  const [form] = Form.useForm();

  const defaultStartAt = dayjs().startOf('days').format();

  const { Status: tranStatus, getStatusText: getTranStatusText } = useTransactionStatus();
  const { Status: tranCallbackStatus, getStatusText: getTranCallbackStatus } = useTransactionCallbackStatus();

  const {
    tableProps,
    searchFormProps,
    filters,
    tableQuery: { data: queryData },
  } = useTable<Transaction, unknown, unknown, Meta>({
    resource: 'transactions',
    syncWithLocation: true,
    filters: {
      permanent: [
        { field: 'started_at', value: defaultStartAt, operator: 'eq' },
        { field: 'confirmed', value: 'created', operator: 'eq' },
        { field: 'lang', value: locale(), operator: 'eq' },
      ],
    },
  });

  const meta = queryData?.meta;

  const columnDeps: ColumnDependencies = {
    t,
    tranStatus,
    getTranStatusText,
    tranCallbackStatus,
    getTranCallbackStatus,
  };
  const columns = useColumns(columnDeps);

  const colProps: ColProps = { xs: 24, sm: 24, md: 12, lg: 6 };

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={
          <ExportButton
            onClick={async () => {
              const url = `${apiUrl}/transaction-report?${queryString.stringify(
                generateFilter(filters)
              )}&token=${getToken()}`;
              window.open(url);
            }}
          >
            {t('export')}
          </ExportButton>
        }
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              form,
              initialValues: {
                started_at: dayjs().startOf('days'),
                confirmed: 'created',
              },
            }}
          >
            <FilterForm form={form} t={t} />
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <Row gutter={16}>
          <Col {...colProps}>
            <Card>
              <Card.Meta
                title={meta?.total}
                description={t('collection.fields.totalNumberOfTransation')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Card.Meta
                title={`${meta?.total_success ?? 0}/${meta?.total ?? 0}`}
                description={`${t('collection.fields.successRate')} ${numeral(
                  ((+meta?.total_success || 0) * 100) / (meta?.total ?? 1)
                ).format('0.00')}%`}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Card.Meta
                title={meta?.total_amount}
                description={t('collection.fields.totalAmountOfTransaction')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Card.Meta
                title={meta?.total_fee}
                description={t('collection.fields.totalFeeOfTranaction')}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
    </>
  );
};

export default CollectionList;
