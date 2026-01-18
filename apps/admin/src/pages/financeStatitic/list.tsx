import {
  List,
} from '@refinedev/antd';
import {
  DatePicker,
  Divider,
  Radio,
  Select,
  TableColumnProps,
} from 'antd';
import useTable from 'hooks/useTable';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import dayjs, { Dayjs } from 'dayjs';
import {
  Daifu,
  Daiso,
  FinanceStatistic,
  Stats,
  Xiafa,
} from 'interfaces/finance';
import { sumBy } from 'lodash';
import numeral from 'numeral';
import CustomDatePicker from 'components/customDatePicker';
import useSelector from 'hooks/useSelector';
import { Merchant } from '@morgan-ustd/shared';
import { useTranslation } from 'react-i18next';

const FinanceStatisticPage: FC = () => {
  const { t } = useTranslation('financeReport');
  const defaultStartAt = dayjs().startOf('days');
  const defaultEndAt = dayjs().endOf('days');
  const { selectProps: merchantSelectProps } = useSelector<Merchant>({
    valueField: 'username',
    resource: 'merchants',
  });
  const { Form, Table, data, form } = useTable<FinanceStatistic>({
    formItems: [
      {
        label: t('filters.startDate'),
        name: 'started_at',
        trigger: 'onSelect',
        children: (
          <CustomDatePicker
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({
                started_at: startAt,
                ended_at: endAt,
              })
            }
          />
        ),
      },
      {
        label: t('filters.endDate'),
        name: 'ended_at',
        children: (
          <DatePicker
            className="w-full"
            disabledDate={current => {
              const startAt = form.getFieldValue('started_at') as Dayjs;
              return (
                current &&
                (current > startAt.add(1, 'month') || current < startAt)
              );
            }}
          />
        ),
      },
      {
        label: t('filters.merchantNameOrAccount'),
        name: 'merchant_name_or_username[]',
        children: <Select {...merchantSelectProps} mode="multiple" />,
      },
      {
        label: t('filters.classification'),
        name: 'timeType',
        children: (
          <Radio.Group>
            <Radio value={'created_at'}>{t('filters.byCreateTime')}</Radio>
            <Radio value={'confirmed_at'}>{t('filters.bySuccessTime')}</Radio>
          </Radio.Group>
        ),
      },
    ],
    filters: [
      {
        field: 'started_at',
        value: defaultStartAt.format(),
        operator: 'eq',
      },
      {
        field: 'ended_at',
        value: defaultEndAt.format(),
        operator: 'eq',
      },
    ],
    hasPagination: false,
  });
  const columns: TableColumnProps<FinanceStatistic>[] = [
    {
      title: t('fields.merchantName'),
      dataIndex: 'name',
    },
    {
      title: t('fields.collectionTotal'),
      dataIndex: ['stats', 'daiso', 'total_amount'],
    },
    {
      title: t('fields.collectionCount'),
      dataIndex: ['stats', 'daiso', 'count'],
    },
    {
      title: t('fields.collectionFee'),
      dataIndex: ['stats', 'daiso', 'total_fee'],
    },
    {
      title: t('fields.collectionProfit'),
      dataIndex: ['stats', 'daiso', 'total_profit'],
    },
    {
      title: t('fields.payoutTotal'),
      dataIndex: ['stats', 'daifu', 'total_amount'],
    },
    {
      title: t('fields.payoutCount'),
      dataIndex: ['stats', 'daifu', 'count'],
    },
    {
      title: t('fields.payoutFee'),
      dataIndex: ['stats', 'daifu', 'total_fee'],
    },
    {
      title: t('fields.payoutProfit'),
      dataIndex: ['stats', 'daifu', 'total_profit'],
    },
    {
      title: t('fields.disbursementTotal'),
      dataIndex: ['stats', 'xiafa', 'total_amount'],
    },
    {
      title: t('fields.disbursementCount'),
      dataIndex: ['stats', 'xiafa', 'count'],
    },
    {
      title: t('fields.disbursementFee'),
      dataIndex: ['stats', 'xiafa', 'total_fee'],
    },
    {
      title: t('fields.disbursementProfit'),
      dataIndex: ['stats', 'xiafa', 'total_profit'],
    },
    {
      title: t('fields.platformCollectionProfit'),
      dataIndex: ['stats', 'daiso', 'system_profit'],
    },
    {
      title: t('fields.platformPayoutProfit'),
      dataIndex: ['stats', 'daifu', 'system_profit'],
    },
    {
      title: t('fields.platformDisbursementProfit'),
      dataIndex: ['stats', 'xiafa', 'system_profit'],
    },
  ];

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
      numeral(
        sumBy(data, record => +record.stats[key1]['system_profit'])
      ).format('0,0.00')
    );
  });

  return (
    <>
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <List title={t('title')}>
        <Form
          initialValues={{
            started_at: defaultStartAt,
            ended_at: defaultEndAt,
            timeType: 'confirmed_at',
          }}
        />
        <Divider />
        <Table
          columns={columns}
          summary={() => (
            <Table.Summary>
              <Table.Summary.Row>
                {stats.map((stat, index) => (
                  <Table.Summary.Cell
                    key={index}
                    index={index}
                    className="font-bold"
                  >
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
