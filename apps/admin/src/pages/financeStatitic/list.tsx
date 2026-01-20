import { List, useTable } from '@refinedev/antd';
import { Divider, Form, Table } from 'antd';
import dayjs from 'dayjs';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { FinanceStatistic, Stats, Daiso, Daifu, Xiafa } from 'interfaces/finance';
import { sumBy } from 'lodash';
import numeral from 'numeral';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';
import FilterForm from './FilterForm';

const FinanceStatisticPage: FC = () => {
  const { t } = useTranslation('financeReport');
  const [form] = Form.useForm();

  const defaultStartAt = dayjs().startOf('days');
  const defaultEndAt = dayjs().endOf('days');

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: queryData },
  } = useTable<FinanceStatistic>({
    syncWithLocation: true,
    pagination: { mode: 'off' },
    filters: {
      permanent: [
        { field: 'started_at', value: defaultStartAt.format(), operator: 'eq' },
        { field: 'ended_at', value: defaultEndAt.format(), operator: 'eq' },
      ],
    },
  });

  const data = queryData?.data ?? [];
  const columnDeps: ColumnDependencies = { t };
  const columns = useColumns(columnDeps);

  // Calculate summary statistics
  const stats: string[] = [''];
  const key1s: (keyof Stats)[] = ['daiso', 'daifu', 'xiafa'];
  const key2s: (keyof Daiso | keyof Daifu | keyof Xiafa)[] = [
    'total_amount',
    'count',
    'total_fee',
    'total_profit',
  ];
  key1s.forEach(key1 => {
    key2s.forEach(key2 => {
      stats.push(
        numeral(sumBy(data, record => +record.stats[key1][key2])).format(
          key2 === 'count' ? '0' : '0,0.00'
        )
      );
    });
  });
  key1s.forEach(key1 => {
    stats.push(
      numeral(sumBy(data, record => +record.stats[key1]['system_profit'])).format('0,0.00')
    );
  });

  return (
    <>
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <List title={t('title')}>
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              form,
              initialValues: {
                started_at: defaultStartAt,
                ended_at: defaultEndAt,
                timeType: 'confirmed_at',
              },
            }}
          >
            <FilterForm form={form} t={t} />
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          rowKey="id"
          summary={() => (
            <Table.Summary>
              <Table.Summary.Row>
                {stats.map((stat, index) => (
                  <Table.Summary.Cell key={index} index={index} className="font-bold">
                    {stat}
                  </Table.Summary.Cell>
                ))}
              </Table.Summary.Row>
            </Table.Summary>
          )}
        />
      </List>
    </>
  );
};

export default FinanceStatisticPage;
