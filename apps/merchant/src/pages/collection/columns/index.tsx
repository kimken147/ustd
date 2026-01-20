import { Badge } from 'antd';
import type { BadgeProps } from 'antd';
import { DateField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, CollectionColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): CollectionColumn[] {
  const { t, tranStatus, getTranStatusText, tranCallbackStatus, getTranCallbackStatus } = deps;

  return [
    {
      title: t('collection.fields.systemTransactionNo'),
      dataIndex: 'system_order_number',
      render: value => value,
    },
    {
      title: t('collection.fields.merchantTransactionNo'),
      dataIndex: 'order_number',
    },
    {
      title: t('collection.fields.merchantNo'),
      dataIndex: ['merchant', 'username'],
    },
    {
      title: t('collection.fields.channels'),
      dataIndex: 'channel_code',
      render: value => t(`channels.${value}`),
    },
    {
      title: t('collection.fields.amount'),
      dataIndex: 'amount',
    },
    {
      title: t('collection.fields.transactionStatus'),
      dataIndex: 'status',
      render(value): JSX.Element {
        let status: BadgeProps['status'];
        if ([tranStatus.成功, tranStatus.手动成功].includes(value)) {
          status = 'success';
        } else if ([tranStatus.付款超时, tranStatus.匹配超时, tranStatus.失败].includes(value)) {
          status = 'error';
        } else if (
          [tranStatus.已建立, tranStatus.匹配中, tranStatus.等待付款, tranStatus.三方处理中].includes(value)
        ) {
          status = 'processing';
        }
        return <Badge status={status} text={getTranStatusText(value)} />;
      },
    },
    {
      title: t('collection.fields.fee'),
      dataIndex: 'fee',
    },
    {
      title: t('collection.fields.realName'),
      dataIndex: 'real_name',
    },
    {
      title: t('collection.fields.callbackStatus'),
      dataIndex: 'notify_status',
      render(value) {
        let status: BadgeProps['status'];
        if ([tranCallbackStatus.成功].includes(value)) {
          status = 'default';
        } else if ([tranCallbackStatus.通知中, tranCallbackStatus.已通知].includes(value)) {
          status = 'processing';
        } else if (tranCallbackStatus.未通知 === value) {
          status = 'default';
        } else if (tranCallbackStatus.失败) {
          status = 'error';
        }
        return <Badge status={status} text={getTranCallbackStatus(value)} />;
      },
    },
    {
      title: t('createAt'),
      dataIndex: 'created_at',
      render: value => <DateField value={value} format={Format} />,
    },
    {
      title: t('confirmAt'),
      dataIndex: 'confirmed_at',
      render: value => (value ? <DateField value={value} format={Format} /> : null),
    },
  ];
}
