import { FC } from 'react';
import { List } from '@refinedev/antd';
import { useTable } from 'hooks/useTable';
import { Col, DatePicker, Divider, Input } from 'antd';
import { useGetIdentity } from '@refinedev/core';
import dayjs, { Dayjs } from 'dayjs';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, MerchantWalletHistory, formValuesToCrudFilters } from '@morgan-ustd/shared';
import ContentHeader from 'components/contentHeader';
import CustomDatePicker from 'components/customDatePicker';
import { useColumns, type ColumnDependencies } from './columns';

const ProviderWalletList: FC = () => {
  const { t } = useTranslation('providers');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('day').format();

  const {
    tableProps,
    searchFormProps,
  } = useTable<MerchantWalletHistory>({
    onSearch: formValuesToCrudFilters,
    resource: 'wallet-histories',
    syncWithLocation: true,
    filters: {
      initial: [
        {
          field: 'started_at',
          value: defaultStartAt,
          operator: 'eq',
        },
        {
          field: 'role',
          value: 2,
          operator: 'eq',
        },
      ],
    },
  });

  const form = searchFormProps.form;

  const columnDeps: ColumnDependencies = {
    t,
    profileRole: profile?.role,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('balanceAdjustment.title')}</title>
      </Helmet>
      <List
        title={<ContentHeader title={t('balanceAdjustment.title')} resource="providers" />}
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              initialValues: { started_at: dayjs().startOf('day') },
            }}
            defaultValues={{ started_at: dayjs().startOf('day') }}
          >
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('walletHistory.startDate')}
                name="started_at"
                rules={[{ required: true }]}
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
                label={t('walletHistory.endDate')}
                name="ended_at"
              >
                <DatePicker
                  showTime
                  needConfirm={false}
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
                label={t('balanceAdjustment.providerNameOrAccount')}
                name="name_or_username"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>
    </>
  );
};

export default ProviderWalletList;
