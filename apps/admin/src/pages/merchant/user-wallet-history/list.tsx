import { FC } from 'react';
import { List, ShowButton, TextField, useTable } from '@refinedev/antd';
import {
  Card,
  Col,
  ColProps,
  DatePicker,
  Divider,
  Input,
  Row,
  Space,
  Statistic,
} from 'antd';
import { useGetIdentity, useOne } from '@refinedev/core';
import { useSearchParams } from 'react-router';
import dayjs, { Dayjs } from 'dayjs';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, User } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';
import useUserWalletStatus from 'hooks/userUserWalletStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import { UserWalletHistory, Meta } from 'interfaces/userWalletHistory';
import { useColumns, type ColumnDependencies } from './columns';

const colProps: ColProps = {
  xs: 24,
  sm: 24,
  md: 12,
  lg: 4,
};

const UserWalletHistoryList: FC = () => {
  const { t } = useTranslation('merchant');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('day');
  const [searchParams] = useSearchParams();
  const userId = searchParams.get('user_id');

  const { result: user } = useOne<User>({
    resource: 'users',
    id: userId || 0,
  });

  const { Select: UserWalletSelect, getUserWalletStatusText } = useUserWalletStatus();

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: tableData },
  } = useTable<UserWalletHistory>({
    resource: `users/${userId}/wallet-histories`,
    syncWithLocation: true,
    filters: {
      initial: [
        {
          field: 'started_at',
          operator: 'eq',
          value: defaultStartAt.format(),
        },
        {
          field: 'user_id',
          operator: 'eq',
          value: userId,
        },
      ],
    },
  });

  const meta = (tableData as any)?.meta as Meta | undefined;
  const form = searchFormProps.form;

  const { modalProps, show, Modal: UpdateModal } = useUpdateModal({
    formItems: [
      {
        label: t('wallet.note'),
        name: 'note',
        children: <Input.TextArea />,
        rules: [{ required: true }],
      },
    ],
  });

  const columnDeps: ColumnDependencies = {
    t,
    profileRole: profile?.role,
    userId: userId || '',
    getUserWalletStatusText,
    show,
  };

  const columns = useColumns(columnDeps);

  if (!userId) return null;

  return (
    <>
      <Helmet>
        <title>{t('titles.userWalletHistory')}</title>
      </Helmet>
      <List
        title={
          <Space align="center">
            <ShowButton size="large" icon={null} recordItemId={user?.id} resource="merchants">
              {user?.name}
            </ShowButton>
            {' - '}
            <TextField value={t('titles.userWalletHistory')} strong className="text-xl" />
          </Space>
        }
      >
        <ListPageLayout>
          <ListPageLayout.Filter
            formProps={{
              ...searchFormProps,
              initialValues: { started_at: defaultStartAt },
            }}
          >
            <Col xs={24} md={6}>
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
            <Col xs={24} md={6}>
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
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.alterationType')} name="type[]">
                <UserWalletSelect mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.note')} name="note">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <Row gutter={[16, 16]}>
          <Col {...colProps}>
            <Card className="border-[#ff4d4f] border-[2.5px]">
              <Statistic
                value={meta?.wallet_balance_total}
                title={t('wallet.totalAmount')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
        </Row>
        <Divider />

        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>
      <UpdateModal {...modalProps} />
    </>
  );
};

export default UserWalletHistoryList;
