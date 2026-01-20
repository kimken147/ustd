import { Button, Popover, Space } from 'antd';
import { DateField, TextField } from '@refinedev/antd';
import { RedoOutlined, StopOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Format } from '@morgan-ustd/shared';
import Badge from 'components/badge';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createChannelColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.channel'),
    dataIndex: 'channel_name',
    responsive: ['md', 'lg', 'xl', 'xxl'],
  };
}

export function createAmountColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.orderAmount'),
    responsive: ['sm', 'md', 'lg', 'xl', 'xxl'],
    render(_value, record) {
      if (record.amount !== record.floating_amount) {
        return (
          <>
            <span className="line-through">{record.amount}</span>{' '}
            <span>{record.floating_amount}</span>
          </>
        );
      }
      return record.amount;
    },
  };
}

export function createTransferNameColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, apiUrl, canEdit, canShowSI, meta, refetch, show, customMutate } = deps;

  return {
    title: t('fields.transferName'),
    dataIndex: 'real_name',
    responsive: ['md', 'lg', 'xl', 'xxl'],
    render(value, record) {
      let name = '';
      if (value) {
        name = `${value}${record.mobile_number && canShowSI ? `(${record.mobile_number})` : ''}`;
      } else {
        name = `${record.mobile_number && canShowSI ? `(${record.mobile_number})` : ''}`;
      }
      const isBanned = meta.banned_realnames.includes(value);
      return (
        <Space>
          <TextField value={name} delete={isBanned} />
          {isBanned ? (
            <Button
              disabled={!canEdit}
              icon={<RedoOutlined className="!text-[#ff4d4f]" />}
              onClick={async () => {
                await customMutate({
                  url: `${apiUrl}/banned/realname/${value}`,
                  method: 'delete',
                  values: {
                    realname: value,
                    type: 1,
                  },
                });
                refetch();
              }}
            />
          ) : value ? (
            <Button
              disabled={!canEdit}
              icon={<StopOutlined className="!text-[#ff4d4f]" />}
              onClick={() => {
                show({
                  title: t('actions.block'),
                  id: 0,
                  filterFormItems: ['note', 'realname', 'type'],
                  initialValues: {
                    realname: value,
                    type: 1,
                  },
                  customMutateConfig: {
                    url: `${apiUrl}/banned/realname`,
                    method: 'post',
                  },
                });
              }}
            />
          ) : null}
        </Space>
      );
    },
  };
}

export function createStatusColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, tranStatus, getTranStatusText } = deps;

  return {
    title: t('fields.status'),
    dataIndex: 'status',
    width: 120,
    fixed: 'right' as const,
    render(value, record): JSX.Element {
      let color = '';
      if ([tranStatus.成功, tranStatus.手动成功].includes(value)) {
        color = '#16a34a';
      } else if ([tranStatus.失败].includes(value)) {
        color = '#ff4d4f';
      } else if ([tranStatus.等待付款, tranStatus.三方处理中].includes(value)) {
        color = '#1677ff';
      } else if ([tranStatus.已建立, tranStatus.匹配中].includes(value)) {
        color = '#ffbe4d';
      } else if (value === tranStatus.匹配超时) {
        color = '#bebebe';
      } else if ([tranStatus.付款超时].includes(value)) {
        color = '#ff4d4f';
        return (
          <Badge
            text={`${getTranStatusText(value)}${record.refunded_at ? '(退)' : ''}`}
            color={color}
          />
        );
      }
      return <Badge text={getTranStatusText(value)} color={color} />;
    },
  };
}

export function createFeeColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.fee'),
    dataIndex: 'fee',
    responsive: ['lg', 'xl', 'xxl'],
  };
}

export function createRemarkColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.remark'),
    dataIndex: 'note',
    responsive: ['xl', 'xxl'],
  };
}

export function createCallbackStatusColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, tranCallbackStatus, getTranCallbackStatus } = deps;

  return {
    title: t('fields.callbackStatus'),
    dataIndex: 'notify_status',
    responsive: ['lg', 'xl', 'xxl'],
    render(value) {
      let color = '';
      if ([tranCallbackStatus.成功].includes(value)) {
        color = '#16a34a';
      } else if (tranCallbackStatus.未通知 === value) {
        color = '#bebebe';
      } else if (tranCallbackStatus.失败 === value) {
        color = '#ff4d4f';
      } else if (
        tranCallbackStatus.已通知 === value ||
        tranCallbackStatus.通知中 === value
      ) {
        color = '#ffbe4d';
      }
      return <Badge text={getTranCallbackStatus(value)} color={color} />;
    },
  };
}

export function createMemberIpColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, apiUrl, canEdit, meta, refetch, show, customMutate } = deps;

  return {
    title: t('fields.memberIp'),
    dataIndex: 'client_ip',
    responsive: ['xl', 'xxl'],
    render(value) {
      if (!value) return '';
      const isBanned = meta.banned_ips.includes(value);
      return (
        <Space>
          <TextField value={value} delete={isBanned} />
          {isBanned ? (
            <Button
              className="!text-[#ff4d4f]"
              disabled={!canEdit}
              icon={<RedoOutlined />}
              onClick={async () => {
                await customMutate({
                  url: `${apiUrl}/banned/ip/${value}`,
                  method: 'delete',
                  values: {
                    ipv4: value,
                    type: 1,
                  },
                });
                refetch();
              }}
            />
          ) : (
            <Button
              className="!text-[#ff4d4f]"
              icon={<StopOutlined />}
              onClick={() =>
                show({
                  title: t('actions.blockIp'),
                  id: 0,
                  filterFormItems: ['note', 'ipv4', 'type'],
                  initialValues: {
                    ipv4: value,
                    type: 1,
                  },
                  customMutateConfig: {
                    url: `${apiUrl}/banned/ip`,
                    method: 'post',
                  },
                })
              }
            />
          )}
        </Space>
      );
    },
  };
}

export function createCreatedAtColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.createdAt'),
    dataIndex: 'created_at',
    width: 160,
    responsive: ['md', 'lg', 'xl', 'xxl'],
    render(value) {
      return value ? <DateField value={value} format={Format} /> : null;
    },
  };
}

export function createConfirmedAtColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.successTime'),
    dataIndex: 'confirmed_at',
    width: 160,
    responsive: ['lg', 'xl', 'xxl'],
    render(value) {
      return value ? <DateField value={value} format={Format} /> : null;
    },
  };
}

export function createRefundInfoColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.refundInfo'),
    responsive: ['xl', 'xxl'],
    render(_value, record) {
      return record.refunded_at !== null ? (
        <Popover
          trigger={'click'}
          content={
            <>
              <p>
                {t('info.refundedBy', {
                  name: record?.refunded_by?.name ?? t('placeholders.none'),
                })}
              </p>
              <p>
                {t('info.refundedAt', {
                  time: record?.refunded_at
                    ? dayjs(record.refunded_at).format(Format)
                    : t('placeholders.none'),
                })}
              </p>
            </>
          }
        >
          <Button disabled={record.refunded_at === null}>{t('actions.view')}</Button>
        </Popover>
      ) : (
        <Button disabled={true}>{t('actions.view')}</Button>
      );
    },
  };
}
