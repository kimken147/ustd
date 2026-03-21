import { Card, Col, Divider, Form, Row, Statistic } from 'antd';
import type { ColProps } from 'antd';
import { ExportButton, List, useTable } from '@refinedev/antd';
import dayjs from 'dayjs';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTransactionStatus, useTransactionCallbackStatus, ListPageLayout, formValuesToCrudFilters } from '@morgan-ustd/shared';
import type { Meta, Transaction } from 'interfaces/transaction';
import numeral from 'numeral';
import { useApiUrl, useTranslate } from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import queryString from 'query-string';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import { useColumns, type ColumnDependencies } from './columns';
import FilterForm from './FilterForm';

const CollectionList: FC = () => {
  const t = useTranslate();
  const title = t('collection.titles.list');
  const apiUrl = useApiUrl();
  const [form] = Form.useForm();

  const defaultStartAt = dayjs().startOf('day').format('YYYY-MM-DDTHH:mm:ss');

  const { Status: tranStatus, getStatusText: getTranStatusText } = useTransactionStatus();
  const { Status: tranCallbackStatus, getStatusText: getTranCallbackStatus } = useTransactionCallbackStatus();

  const {
    tableProps,
    searchFormProps,
    filters,
    tableQuery: { data: queryData },
  } = useTable<Transaction>({
    onSearch: formValuesToCrudFilters,
    resource: 'transactions',
    syncWithLocation: true,
    filters: {
      permanent: [
        { field: 'started_at', value: defaultStartAt, operator: 'eq' },
        { field: 'confirmed', value: 'created', operator: 'eq' },
      ],
    },
  });

  const meta = (queryData as any)?.meta as Meta | undefined;

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
              const res = await axiosInstance.get(url, { responseType: 'blob' });
              const disposition = res.headers['content-disposition'] || '';
              const utf8Match = disposition.match(/filename\*=UTF-8''(.+)/i);
              const plainMatch = disposition.match(/filename="?([^";\n]+)"?/);
              const filename = utf8Match ? decodeURIComponent(utf8Match[1]) : plainMatch?.[1] || 'report.csv';
              const link = document.createElement('a');
              link.href = URL.createObjectURL(res.data);
              link.download = filename;
              link.click();
              URL.revokeObjectURL(link.href);
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
              <Statistic
                value={meta?.total}
                title={t('collection.fields.totalNumberOfTransation')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={`${meta?.total_success ?? 0}/${meta?.total ?? 0}`}
                title={`${t('collection.fields.successRate')} ${numeral(
                  ((+(meta?.total_success ?? 0)) * 100) / (meta?.total ?? 1)
                ).format('0.00')}%`}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.total_amount}
                title={t('collection.fields.totalAmountOfTransaction')}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card>
              <Statistic
                value={meta?.total_fee}
                title={t('collection.fields.totalFeeOfTranaction')}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns as any} rowKey="id" />
      </List>
    </>
  );
};

export default CollectionList;
