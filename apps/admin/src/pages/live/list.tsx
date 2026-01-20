import { EditOutlined } from '@ant-design/icons';
import { List, useTable } from '@refinedev/antd';
import {
  Card,
  Checkbox,
  Col,
  Divider,
  Row,
  Space,
  Statistic,
  Switch,
  Typography,
} from 'antd';
import { useApiUrl, useCustom } from '@refinedev/core';
import useChannel from 'hooks/useChannel';
import type { UserChannelStat, Channel as ChannelStat } from 'interfaces/userChannelStat';
import { FC, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import type { CheckboxOptionType } from 'antd';
import type { OnlinMatchingUser } from 'interfaces/onlineMatchingForUser';
import useUpdateModal from 'hooks/useUpdateModal';
import useSystemSetting from 'hooks/useSystemSetting';
import { ListPageLayout } from '@morgan-ustd/shared';
import Enviroment from 'lib/env';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

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
  const { result: customResult } = useCustom<UserChannelStat>({
    url: `${apiUrl}/user-channel-account-stats`,
    config: {
      query: channels?.length
        ? { 'channel_code[]': channels?.map((channel: { code: string }) => channel.code) }
        : undefined,
    },
    queryOptions: {
      queryKey: ['user-channel-account-stats', channelCodes],
      refetchInterval: autoRefetch ? refetchFreq * 1000 : undefined,
    },
    method: 'get',
  });
  const accountStat = customResult?.data;

  const [indeterminate, setIndeterminate] = useState(false);
  const [checkedList, setCheckedList] = useState<CheckboxOptionType['value'][]>([]);
  const [checkAll, setCheckAll] = useState(false);

  const { data: systemSetting } = useSystemSetting();
  const dayEnable = systemSetting?.find(x => x.id === 35)?.enabled;
  const monthEnable = systemSetting?.find(x => x.id === 45)?.enabled;

  const { Modal } = useUpdateModal();

  const {
    tableProps,
    tableQuery: { refetch },
  } = useTable<OnlinMatchingUser>({
    resource: 'online-ready-for-matching-users',
    syncWithLocation: true,
    pagination: { mode: 'off' },
    filters: {
      permanent: channelCodes.map(code => ({
        field: 'channel_code[]',
        value: code,
        operator: 'eq' as const,
      })),
    },
    queryOptions: {
      refetchInterval: autoRefetch ? refetchFreq * 1000 : undefined,
    },
  });

  const columnDeps: ColumnDependencies = {
    t,
    isPaufen,
    dayEnable,
    monthEnable,
    Modal,
    refetch,
  };
  const columns = useColumns(columnDeps);

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
          {accountStat?.channels?.map(({ channel_name, paying }: ChannelStat) => (
            <Col xs={12} md={6} lg={4} xl={3} xxl={2} key={channel_name} className="mt-4">
              <Card size="small" className="border-[#1677ff] border-[2.5px]">
                <Statistic
                  title={channel_name}
                  value={paying}
                  valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
                />
              </Card>
            </Col>
          ))}
          <Col xs={12} md={6} lg={4} xl={3} xxl={2} key={t('statistics.payoutOrders')}>
            <Card size="small" className="border-[#f7b801] mt-4 border-[2.5px]">
              <Statistic
                title={t('statistics.payoutOrders')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
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
                  icon: <EditOutlined style={{ color: '#6eb9ff' }} />,
                }}
              >
                {refetchFreq.toString()}
              </Typography.Text>
              {')'}
              <Switch checked={autoRefetch} onChange={check => setAutoRefetch(check)} />
            </Space>
          </Space>
          <Divider />
          <Checkbox.Group
            className="w-full"
            value={checkedList}
            onChange={values => {
              if (!channels?.length) return;
              setCheckedList(values);
              setIndeterminate(!!checkedList?.length && checkedList?.length < channels.length);
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
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="user_channel_accounts_id" />
      </List>
    </>
  );
};

export default LiveList;
