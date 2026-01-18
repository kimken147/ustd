import { EditOutlined } from '@ant-design/icons';
import {
  Button,
  Card,
  Checkbox,
  Col,
  Divider,
  List,
  Row,
  Space,
  Statistic,
  Switch,
  TableColumnProps,
  Typography,
} from '@refinedev/antd';
import { useApiUrl, useCustom } from '@refinedev/core';
import useChannel from 'hooks/useChannel';
import { UserChannelStat } from 'interfaces/userChannelStat';
import { FC, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import type { CheckboxOptionType } from '@refinedev/antd';
import useTable from 'hooks/useTable';
import { OnlinMatchingUser } from 'interfaces/onlineMatchingForUser';
import useUpdateModal from 'hooks/useUpdateModal';
import useSystemSetting from 'hooks/useSystemSetting';
import numeral from 'numeral';
import Table from 'components/table';
import Enviroment from 'lib/env';
import { useTranslation } from 'react-i18next';

const LiveList: FC = () => {
  const { t } = useTranslation('live');
  const isPaufen = Enviroment.isPaufen;
  const { channels } = useChannel();
  const [autoRefetch, setAutoRefetch] = useState(false);
  const [refetchFreq, setRefetchFreq] = useState(10);
  const channelCodes = useMemo(() => {
    return channels?.map(channel => channel.code) || [];
  }, [channels]);
  const apiUrl = useApiUrl();
  const { data } = useCustom<UserChannelStat>({
    url: `${apiUrl}/user-channel-account-stats`,
    config: {
      query: channels?.length
        ? {
            'channel_code[]': channels?.map(channel => channel.code),
          }
        : undefined,
    },
    queryOptions: {
      refetchInterval: autoRefetch ? refetchFreq * 1000 : undefined,
    },
    method: 'get',
  });
  const accountStat = data?.data;
  const [indeterminate, setIndeterminate] = useState(false);
  const [checkedList, setCheckedList] = useState<CheckboxOptionType['value'][]>(
    []
  );
  const [checkAll, setCheckAll] = useState(false);
  const { data: systemSetting } = useSystemSetting();
  const dayEnable = systemSetting?.find(x => x.id === 35)?.enabled;
  const monthEnable = systemSetting?.find(x => x.id === 45)?.enabled;

  const day: TableColumnProps<OnlinMatchingUser>[] = dayEnable
    ? [
        {
          title: t('fields.dailyCollectionLimit'),
          render(value, record, index) {
            return `${numeral(record.daily_limit).format('0.00')}/${numeral(
              record.daily_total
            ).format('0.00')}`;
          },
        },
        {
          title: t('fields.dailyPayoutLimit'),
          render(value, record, index) {
            return `${numeral(record.withdraw_daily_limit).format('0.00')}/${numeral(
              record.withdraw_daily_total
            ).format('0.00')}`;
          },
        },
      ]
    : [];

  const month: TableColumnProps<OnlinMatchingUser>[] = monthEnable
    ? [
        {
          title: t('fields.monthlyCollectionLimit'),
          render(value, record, index) {
            return `${numeral(record.monthly_limit).format('0.00')}/${numeral(
              record.monthly_total
            ).format('0.00')}`;
          },
        },
        {
          title: t('fields.monthlyPayoutLimit'),
          render(value, record, index) {
            return `${numeral(record.withdraw_monthly_limit).format('0.00')}/${numeral(
              record.withdraw_monthly_total
            ).format('0.00')}`;
          },
        },
      ]
    : [];
  const avialableBalanceColumn: TableColumnProps<OnlinMatchingUser>[] = isPaufen
    ? [
        {
          title: t('fields.availableBalance'),
          dataIndex: 'available_balance',
        },
      ]
    : [];

  const balanceColumn: TableColumnProps<OnlinMatchingUser>[] = !isPaufen
    ? [
        {
          title: t('fields.balance'),
          dataIndex: 'balance',
          sorter: (a, b) => +a.balance - +b.balance,
        },
      ]
    : [];

  const columns: TableColumnProps<OnlinMatchingUser>[] = [
    {
      title: isPaufen ? t('fields.providerAccount') : t('fields.groupAccount'),
      dataIndex: 'name',
    },
    ...avialableBalanceColumn,
    {
      title: t('fields.account'),
      dataIndex: 'user_channel_accounts',
    },
    // {
    //     title: "类型",
    //     dataIndex: "type",
    //     render: (value) => {
    //         return getChannelTypeText(value);
    //     },
    //     sorter: (a, b) => a.type - b.type,
    // },
    {
      title: t('fields.accountNumber'),
      dataIndex: 'hash_id',
    },
    ...balanceColumn,
    {
      title: t('fields.collectionInProgress'),
      render(value, record, index) {
        let singleLimit = '';
        if (
          !(
            record.single_min_limit === null ||
            record.single_min_limit === undefined
          ) ||
          !(
            record.single_max_limit === null ||
            record.single_max_limit === undefined
          )
        ) {
          let singleMinLimit =
            record.single_min_limit === null ||
            record.single_min_limit === undefined
              ? ''
              : record.single_min_limit;
          let singleMaxLimit =
            record.single_max_limit === null ||
            record.single_max_limit === undefined
              ? ''
              : record.single_max_limit;
          singleLimit += ` -(${singleMinLimit}~${singleMaxLimit})`;
        }
        return `${record.paying_balance}(${record.total_paying_count})${singleLimit}`;
      },
    },
    {
      title: t('fields.payoutInProgress'),
      render(value, record, index) {
        return `${record.withdraw_balance}(${record.total_withdraw_count})`;
      },
    },
    ...day,
    ...month,
    {
      title: t('fields.operation'),
      render(value, record, index) {
        return (
          <Button
            danger
            type="primary"
            onClick={() =>
              Modal.confirm({
                title: t('actions.confirmOffline'),
                id: record.user_channel_accounts_id,
                resource: 'user-channel-accounts',
                values: {
                  status: 1,
                },
                onSuccess() {
                  refetch();
                },
              })
            }
          >
            {t('actions.offline')}
          </Button>
        );
      },
    },
  ];

  const { refetch, tableProps } = useTable<OnlinMatchingUser>({
    resource: 'online-ready-for-matching-users',
    filters: [
      {
        operator: 'or',
        value: channelCodes.map(code => ({
          field: 'channel_code[]',
          value: code,
          operator: 'eq',
        })),
      },
    ],
    hasPagination: false,
    queryOptions: {
      refetchInterval: autoRefetch ? refetchFreq * 1000 : undefined,
    },
    showError: false,
    tableProps: {
      rowKey: 'user_channel_accounts_id',
      columns,
    },
  });

  const { Modal } = useUpdateModal();

  useEffect(() => {
    if (channels?.length) {
      setCheckedList(channelCodes);
      setIndeterminate(false);
      setCheckAll(true);
    }
  }, [channelCodes, channels]);

  return (
    <>
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <List title={t('title')}>
        <Row gutter={16}>
          {accountStat?.channels?.map(({ channel_name, paying }) => (
            <Col
              xs={12}
              md={6}
              lg={4}
              xl={3}
              xxl={2}
              key={channel_name}
              className={'mt-4'}
            >
              <Card size="small" className="border-[#1677ff] border-[2.5px]">
                <Statistic
                  title={channel_name}
                  value={paying}
                  valueStyle={{
                    fontStyle: 'italic',
                    fontWeight: 'bold',
                  }}
                />
              </Card>
            </Col>
          ))}
          <Col
            xs={12}
            md={6}
            lg={4}
            xl={3}
            xxl={2}
            key={t('statistics.payoutOrders')}
          >
            <Card size="small" className="border-[#f7b801] mt-4 border-[2.5px]">
              <Statistic
                title={t('statistics.payoutOrders')}
                valueStyle={{
                  fontStyle: 'italic',
                  fontWeight: 'bold',
                }}
                value={accountStat?.withdraw_orders}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <Typography.Title level={5}>{t('queue.title')}</Typography.Title>
        <Card>
          <Space>
            <Checkbox
              indeterminate={indeterminate}
              checked={checkAll}
              onChange={e => {
                if (!channels?.length) return;
                setCheckedList(e.target.checked ? channelCodes : []);
                setIndeterminate(false);
                setCheckAll(e.target.checked);
              }}
            >
              {t('queue.selectAll')}
            </Checkbox>
            <Space align="center">
              {t('queue.autoRefresh')}
              {'('}
              <Typography.Text
                editable={{
                  onChange(value) {
                    if (!Number.isInteger(+value)) return;
                    else setRefetchFreq(+value);
                  },
                  icon: (
                    <EditOutlined
                      style={{
                        color: '#6eb9ff',
                      }}
                    />
                  ),
                }}
              >
                {refetchFreq.toString()}
              </Typography.Text>
              {')'}
              <Switch
                checked={autoRefetch}
                onChange={check => setAutoRefetch(check)}
              />
            </Space>
          </Space>
          <Divider />
          <Checkbox.Group
            className="w-full"
            value={checkedList}
            onChange={values => {
              if (!channels?.length) return;
              setCheckedList(values);
              setIndeterminate(
                !!checkedList?.length && checkedList?.length < channels.length
              );
              setCheckAll(checkedList.length === channels.length);
            }}
          >
            <Row className="w-full">
              {channels?.map(channel => (
                <Col xs={8} md={6} lg={4} xl={3} xxl={2} key={channel.code}>
                  <Checkbox value={channel.code}>{channel.name}</Checkbox>
                </Col>
              ))}
            </Row>
          </Checkbox.Group>
        </Card>
        <Table {...tableProps} />
      </List>
    </>
  );
};

export default LiveList;
