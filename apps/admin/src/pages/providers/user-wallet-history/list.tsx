import { FC } from 'react';
import { List, ShowButton, TextField, useTable } from '@refinedev/antd';
import { Col, DatePicker, Divider, Input, Space } from 'antd';
import { useGetIdentity, useOne } from '@refinedev/core';
import { useSearchParams } from 'react-router';
import dayjs, { Dayjs } from 'dayjs';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, User } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';
import useUserWalletStatus from 'hooks/userUserWalletStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import { UserWalletHistory } from 'interfaces/userWalletHistory';
import { useColumns, type ColumnDependencies } from './columns';

const ProviderUserWalletHistoryList: FC = () => {
  const { t } = useTranslation('providers');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('days');
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

  const form = searchFormProps.form;

  const { modalProps, show, Modal: UpdateModal } = useUpdateModal({
    formItems: [
      {
        label: t('walletHistory.note'),
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
        <title>{t('walletHistory.title')}</title>
      </Helmet>
      <List
        title={
          <Space align="center">
            <ShowButton size="large" icon={null} recordItemId={user?.id} resource="providers">
              {user?.name}
            </ShowButton>
            {' - '}
            <TextField value={t('walletHistory.title')} strong className="text-xl" />
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
                label={t('walletHistory.startDate')}
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
                label={t('walletHistory.endDate')}
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
              <ListPageLayout.Filter.Item label={t('walletHistory.alterationType')} name="type[]">
                <UserWalletSelect mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('walletHistory.note')} name="note">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} />
      </List>
      <UpdateModal {...modalProps} />
    </>
  );
};

export default ProviderUserWalletHistoryList;
