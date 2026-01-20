/**
 * Status columns for PayForAnother list
 * - Order status
 * - Callback status
 */
import Badge from 'components/badge';
import type { ColumnContext, WithdrawColumn } from './types';

export function createOrderStatusColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, getWithdrawStatusText, WithdrawStatus } = ctx;

  return {
    title: t('fields.orderStatus'),
    dataIndex: 'status',
    render(value) {
      let color = '';
      if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
        color = '#16a34a';
      } else if ([WithdrawStatus.支付超时, WithdrawStatus.失败].includes(value)) {
        color = '#ff4d4f';
      } else if (
        [WithdrawStatus.审核中, WithdrawStatus.等待付款, WithdrawStatus.三方处理中].includes(value)
      ) {
        color = '#1677ff';
      } else if (value === WithdrawStatus.匹配中) {
        color = '#ffbe4d';
      } else if (value === WithdrawStatus.匹配超时) {
        color = '#bebebe';
      }
      return <Badge text={getWithdrawStatusText(value)} color={color} />;
    },
  };
}

export function createCallbackStatusColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, getTranCallbackStatus, tranCallbackStatus } = ctx;

  return {
    title: t('fields.callbackStatus'),
    dataIndex: 'notify_status',
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

/**
 * Get all status columns
 */
export function getStatusColumns(ctx: ColumnContext): WithdrawColumn[] {
  return [
    createOrderStatusColumn(ctx),
    createCallbackStatusColumn(ctx),
  ];
}
