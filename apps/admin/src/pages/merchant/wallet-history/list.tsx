import { InfoCircleOutlined } from '@ant-design/icons';
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
  Select,
  Space,
  Statistic,
  TableColumnProps,
} from 'antd';
import { useGetIdentity } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import {
  Merchant,
  MerchantWalletHistory as MerchantWallet,
  MerchantWalletOperator as Operator,
  MerchantWalletUser as User,
  MerchantWalletMeta as Meta,
  Format,
} from '@morgan-ustd/shared';
import { getSign } from 'lib/number';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const MerchantWalletList: FC = () => {
  const { t } = useTranslation('merchant');
  const { data: profile } = useGetIdentity<Profile>();
  const { selectProps: merchantSelectProps } = useSelector<Merchant>({
    resource: 'merchants',
    valueField: 'name',
  });
  const defaultStartAt = dayjs().startOf('day');

  const { Form, Table, form, meta } = useTable<MerchantWallet, Meta>({
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
        label: t('fields.merchantOrAccount'),
        name: 'name_or_username',
        children: <Select {...merchantSelectProps} />,
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
        value: 3,
        operator: 'eq',
      },
    ],
  });

  const columns: TableColumnProps<MerchantWallet>[] = [
    {
      title: t('fields.name'),
      dataIndex: 'user',
      render(value: User) {
        return (
          <ShowButton
            recordItemId={value.id}
            icon={null}
            resource="merchants"
          >
            {value.name}
          </ShowButton>
        );
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
    },
    {
      title: t('wallet.alterationTime'),
      dataIndex: 'created_at',
      render(value) {
        return <DateField value={value} format={Format} />;
      },
    },
    {
      title: t('wallet.operator'),
      dataIndex: 'operator',
      render(value: Operator) {
        return (
          <Space>
            {value.role === 1 ? (
              <TextField value={value.username} />
            ) : (
              <ShowButton
                recordItemId={value.id}
                disabled={profile?.role !== 1}
                resource="sub-accounts"
                icon={null}
              >
                {value.username}
              </ShowButton>
            )}
            <Popover
              trigger={'click'}
              content={
                <TextField
                  value={t('wallet.operatorInfo', { name: value.name })}
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

  const colProps: ColProps = {
    xs: 24,
    sm: 24,
    md: 12,
    lg: 4,
  };

  return (
    <>
      <Helmet>
        <title>{t('titles.walletHistory')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader
            title={t('titles.walletHistory')}
            resource="merchants"
          />
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
        <Table columns={columns} />
      </List>
    </>
  );
};

export default MerchantWalletList;
