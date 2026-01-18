import { InfoCircleOutlined } from '@ant-design/icons';
import {
  DateField,
  List,
  ShowButton,
  TextField,
} from '@refinedev/antd';
import {
  DatePicker,
  Divider,
  Input,
  Popover,
  Space,
  TableColumnProps,
} from 'antd';
import { useGetIdentity } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useTable from 'hooks/useTable';
import {
  MerchantWalletHistory as MerchantWallet,
  MerchantWalletOperator as Operator,
  MerchantWalletUser as User,
  Format,
} from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import numeral from 'numeral';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const ProviderWalletList: FC = () => {
  const { t } = useTranslation('providers');
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('days');
  const { Form, Table, form } = useTable<MerchantWallet>({
    formItems: [
      {
        label: t('walletHistory.startDate'),
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
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('walletHistory.endDate'),
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
        label: t('balanceAdjustment.providerNameOrAccount'),
        name: 'name_or_username',
        children: <Input />,
      },
    ],
    filters: [
      {
        field: 'started_at',
        value: defaultStartAt.format(),
        operator: 'eq',
      },
      {
        field: 'role',
        value: 2,
        operator: 'eq',
      },
    ],
  });
  const columns: TableColumnProps<MerchantWallet>[] = [
    {
      title: t('balanceAdjustment.providerName'),
      dataIndex: 'user',
      render(value: User, record, index) {
        return (
          <ShowButton
            recordItemId={value.id}
            icon={null}
            resourceNameOrRouteName="merchants"
          >
            {value.name}
          </ShowButton>
        );
      },
    },
    {
      title: t('walletHistory.balanceDelta'),
      dataIndex: 'balance_delta',
      render(value, record, index) {
        return getSign(value);
      },
    },
    {
      title: t('walletHistory.profitDelta'),
      dataIndex: 'profit_delta',
      render(value, record, index) {
        let color = '';
        const amount = numeral(value).value();
        if (amount !== null) {
          if (amount > 0) color = 'text-[#16A34A]';
          else if (amount < 0) color = 'text-[#FF4D4F]';
        }
        return <TextField value={value} className={color} />;
      },
    },
    {
      title: t('walletHistory.frozenBalanceDelta'),
      dataIndex: 'frozen_balance_delta',
      render(value, record, index) {
        return getSign(value);
      },
    },
    {
      title: t('walletHistory.balanceResult'),
      dataIndex: 'balance_result',
    },
    {
      title: t('walletHistory.profitResult'),
      dataIndex: 'profit_result',
    },
    {
      title: t('walletHistory.frozenBalanceResult'),
      dataIndex: 'frozen_balance_result',
    },
    {
      title: t('walletHistory.note'),
      dataIndex: 'note',
    },
    {
      title: t('walletHistory.alterationTime'),
      dataIndex: 'created_at',
      render(value, record, index) {
        return <DateField value={value} format={Format} />;
      },
    },
    {
      title: t('walletHistory.operator'),
      dataIndex: 'operator',
      render(value: Operator, record, index) {
        return (
          <Space>
            {value.role === 1 ? (
              <TextField value={value.username} />
            ) : (
              <ShowButton
                recordItemId={value.id}
                disabled={profile?.role !== 1}
                resourceNameOrRouteName="sub-accounts"
                icon={null}
              >
                {value.username}
              </ShowButton>
            )}

            <Popover
              trigger={'click'}
              content={
                <TextField
                  value={t('walletHistory.operatorInfo', { name: value.name })}
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
        <title>{t('balanceAdjustment.title')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader
            title={t('balanceAdjustment.title')}
            resource="providers"
          />
        }
      >
        <Form
          initialValues={{
            started_at: defaultStartAt,
          }}
        />
        <Divider />
        <Table columns={columns} />
      </List>
    </>
  );
};

export default ProviderWalletList;
