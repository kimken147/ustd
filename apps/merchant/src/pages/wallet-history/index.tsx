import { Card, Col, Descriptions, Divider, Form, Row, Statistic } from 'antd';
import { ExportButton, List, useTable } from '@refinedev/antd';
import { useApiUrl, useGetLocale, useTranslate } from '@refinedev/core';
import { getToken } from 'authProvider';
import { generateFilter } from 'dataProvider';
import dayjs from 'dayjs';
import useProfile from 'hooks/useProfile';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { Meta, WalletHistory } from 'interfaces/wallet-history';
import queryString from 'query-string';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns, type ColumnDependencies } from './columns';
import FilterForm from './FilterForm';

const Status = {
  系统调整: 1,
  余额转赠: 2,
  入帐: 3,
  预扣: 4,
  预扣退款: 5,
  快充奖励: 6,
  交易奖励: 7,
  失败: 8,
  '系统调整(冻结)': 11,
  提现: 12,
  提现退款: 13,
  入帐退款: 14,
};

const WalletHistoryList: FC = () => {
  const t = useTranslate();
  const locale = useGetLocale();
  const title = t('walletHistory.titles.list');
  const apiUrl = useApiUrl();
  const [form] = Form.useForm();
  const defaultStartAt = dayjs().startOf('days');
  const { data: profile } = useProfile();

  const {
    tableProps,
    searchFormProps,
    filters,
    tableQuery: { data: queryData },
  } = useTable<WalletHistory, unknown, unknown, Meta>({
    syncWithLocation: true,
    filters: {
      permanent: [{ field: 'lang', value: locale(), operator: 'eq' }],
    },
  });

  const meta = queryData?.meta;
  const columnDeps: ColumnDependencies = { t };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={() => (
          <ExportButton
            onClick={async () => {
              const url = `${apiUrl}/wallet-histories-report?${queryString.stringify(
                generateFilter(filters)
              )}&token=${getToken()}`;
              window.open(url);
            }}
          >
            {t('export')}
          </ExportButton>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              form,
              initialValues: { started_at: defaultStartAt },
            }}
          >
            <FilterForm form={form} t={t} Status={Status} />
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <Card>
          <Descriptions column={{ xs: 1, md: 3 }} bordered title={t('home.fields.balance')}>
            <Descriptions.Item label={t('home.fields.balance')}>
              {profile?.wallet.balance}
            </Descriptions.Item>
            <Descriptions.Item label={t('home.fields.availableBalance')}>
              {profile?.wallet.available_balance}
            </Descriptions.Item>
            <Descriptions.Item label={t('home.fields.frozenBalance')}>
              {profile?.wallet.frozen_balance}
            </Descriptions.Item>
          </Descriptions>
        </Card>
        <Divider />
        <Row gutter={16}>
          <Col xs={24} md={6}>
            <Card>
              <Statistic title={t('walletHistory.fields.totalAmount')} value={meta?.wallet_balance_total || 0} />
            </Card>
          </Col>
        </Row>
        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
    </>
  );
};

export default WalletHistoryList;
