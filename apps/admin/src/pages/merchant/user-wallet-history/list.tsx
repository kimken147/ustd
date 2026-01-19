import { EditOutlined, InfoCircleOutlined } from '@ant-design/icons';
import {
  DateField,
  List,
  ShowButton,
  TextField,
} from '@refinedev/antd';
import {
  Card,
  Col,
  ColProps,
  DatePicker,
  Divider,
  Input,
  Popover,
  Row,
  Space,
  Statistic,
  TableColumnProps,
} from 'antd';
import { useGetIdentity, useOne } from '@refinedev/core';
import { useSearchParams } from 'react-router';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useUserWalletStatus from 'hooks/userUserWalletStatus';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { MerchantWalletOperator as Operator, User, Format } from '@morgan-ustd/shared';
import { UserWalletHistory, Meta } from 'interfaces/userWalletHistory';
import { getSign } from 'lib/number';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const UserWalletHistoryList: FC = () => {
  const { t } = useTranslation('merchant');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('day');
  const [searchParams] = useSearchParams();
  const userId = searchParams.get('user_id');

  const colProps: ColProps = {
    xs: 24,
    sm: 24,
    md: 12,
    lg: 4,
  };

  const { result: user } = useOne<User>({
    resource: 'users',
    id: userId || 0,
  });
  const { Select: UserWalletSelect, getUserWalletStatusText } =
    useUserWalletStatus();

  const { Form, Table, form, meta } = useTable<UserWalletHistory, Meta>({
    resource: `users/${userId}/wallet-histories`,
    filters: [
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
    formItems: [
      {
        label: t('filters.startDate'),
        name: 'started_at',
        trigger: 'onSelect',
        children: (
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({
                started_at: startAt,
                ended_at: endAt,
              })
            }
          />
        ),
        rules: [{ required: true }],
      },
      {
        label: t('filters.endDate'),
        name: 'ended_at',
        trigger: 'onSelect',
        children: (
          <DatePicker
            showTime
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
        label: t('filters.alterationType'),
        name: 'type[]',
        children: <UserWalletSelect mode="multiple" />,
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
    ],
  });

  const { Modal, show } = useUpdateModal({
    formItems: [
      {
        label: t('wallet.note'),
        name: 'note',
        children: <Input.TextArea />,
        rules: [{ required: true }],
      },
    ],
  });

  if (!userId) return null;

  const columns: TableColumnProps<UserWalletHistory>[] = [
    {
      title: t('filters.alterationType'),
      dataIndex: 'type',
      render(value) {
        return getUserWalletStatusText(value);
      },
    },
    {
      title: t('wallet.balanceDelta'),
      dataIndex: 'balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('wallet.frozenBalanceDelta'),
      dataIndex: 'frozen_balance_delta',
      render(value) {
        return getSign(value);
      },
    },
    {
      title: t('wallet.balanceResult'),
      dataIndex: 'balance_result',
    },
    {
      title: t('wallet.frozenBalanceResult'),
      dataIndex: 'frozen_balance_result',
    },
    {
      title: t('wallet.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: t('wallet.editNote'),
                  id: record.id,
                  resource: `users/${userId}/wallet-histories`,
                  filterFormItems: ['note'],
                  initialValues: { note: record.note },
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('wallet.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: t('wallet.operator'),
      dataIndex: 'operator',
      render(value: Operator) {
        if (!value) return null;
        return (
          <Space>
            {value?.role === 1 ? (
              <TextField value={value?.username} />
            ) : (
              <ShowButton
                recordItemId={value?.id}
                disabled={profile?.role !== 1}
                resource="sub-accounts"
                icon={null}
              >
                {value?.username}
              </ShowButton>
            )}
            <Popover
              trigger={'click'}
              content={
                <TextField
                  value={t('wallet.operatorInfo', { name: value?.name })}
                />
              }
            >
              <InfoCircleOutlined className="text-[#1677ff]" />
            </Popover>
          </Space>
        );
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('titles.userWalletHistory')}</title>
      </Helmet>
      <List
        title={
          <Space align="center">
            <ShowButton
              size="large"
              icon={null}
              recordItemId={user?.id}
              resource="merchants"
            >
              {user?.name}
            </ShowButton>
            {' - '}
            <TextField
              value={t('titles.userWalletHistory')}
              strong
              className="text-xl"
            />
          </Space>
        }
      >
        <Form
          initialValues={{
            started_at: defaultStartAt,
          }}
        />
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
        <Table columns={columns} />
      </List>
      <Modal />
    </>
  );
};

export default UserWalletHistoryList;
