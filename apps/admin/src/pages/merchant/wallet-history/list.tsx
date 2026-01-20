import { FC } from 'react';
import { List, useTable } from '@refinedev/antd';
import { Card, Col, ColProps, DatePicker, Divider, Row, Select, Statistic } from 'antd';
import { useGetIdentity } from '@refinedev/core';
import dayjs, { Dayjs } from 'dayjs';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import {
  ListPageLayout,
  Merchant,
  MerchantWalletHistory,
  MerchantWalletMeta as Meta,
} from '@morgan-ustd/shared';
import ContentHeader from 'components/contentHeader';
import CustomDatePicker from 'components/customDatePicker';
import useSelector from 'hooks/useSelector';
import { useColumns, type ColumnDependencies } from './columns';

const colProps: ColProps = {
  xs: 24,
  sm: 24,
  md: 12,
  lg: 4,
};

const MerchantWalletList: FC = () => {
  const { t } = useTranslation('merchant');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('day');

  const { selectProps: merchantSelectProps } = useSelector<Merchant>({
    resource: 'merchants',
    valueField: 'name',
  });

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: tableData },
  } = useTable<MerchantWalletHistory>({
    resource: 'wallet-histories',
    syncWithLocation: true,
    filters: {
      initial: [
        {
          field: 'started_at',
          value: defaultStartAt.format(),
          operator: 'eq',
        },
        {
          field: 'role',
          value: 3,
          operator: 'eq',
        },
      ],
    },
  });

  const meta = (tableData as any)?.meta as Meta | undefined;
  const form = searchFormProps.form;

  const columnDeps: ColumnDependencies = {
    t,
    profileRole: profile?.role,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('titles.walletHistory')}</title>
      </Helmet>
      <List
        title={<ContentHeader title={t('titles.walletHistory')} resource="merchants" />}
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              initialValues: { started_at: defaultStartAt },
            }}
          >
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('filters.startDate')}
                name="started_at"
                rules={[{ required: true }]}
                trigger="onSelect"
              >
                <CustomDatePicker
                  showTime
                  className="w-full"
                  onFastSelectorChange={(startAt, endAt) =>
                    form?.setFieldsValue({
                      started_at: startAt,
                      ended_at: endAt,
                    })
                  }
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('filters.endDate')}
                name="ended_at"
                trigger="onSelect"
              >
                <DatePicker
                  showTime
                  className="w-full"
                  disabledDate={current => {
                    const startAt = form?.getFieldValue('started_at') as Dayjs;
                    return (
                      current &&
                      startAt &&
                      (current > startAt.add(1, 'month') || current < startAt)
                    );
                  }}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('fields.merchantOrAccount')}
                name="name_or_username"
              >
                <Select {...merchantSelectProps} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <Row gutter={[16, 16]}>
          <Col {...colProps}>
            <Card className="border-[#ff4d4f] border-[2.5px]">
              <Statistic
                value={meta?.total_increased_balance_delta}
                title={t('wallet.balanceIncrease')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#7fd1b9] border-[2.5px]">
              <Statistic
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
                value={meta?.total_decreased_balance_delta}
                title={t('wallet.balanceDecrease')}
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

export default MerchantWalletList;
