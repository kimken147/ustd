/**
 * Data columns for PayForAnother list
 * - Order amount
 * - Fee
 * - Created at
 * - Confirmed at
 * - System order number
 */
import { Typography } from 'antd';
import { DateField } from '@refinedev/antd';
import { CopyOutlined } from '@ant-design/icons';
import type { ColumnContext, WithdrawColumn } from './types';

export function createAmountColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.orderAmount'),
    dataIndex: 'amount',
  };
}

export function createFeeColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.fee'),
    dataIndex: 'merchant_fees',
    render(value) {
      return value?.length ? value[value.length - 1].actual_fee : 0;
    },
  };
}

export function createCreatedAtColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.createdAt'),
    dataIndex: 'created_at',
    render(value) {
      return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
    },
  };
}

export function createConfirmedAtColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.successTime'),
    dataIndex: 'confirmed_at',
    render(value) {
      return value ? <DateField value={value} format="YYYY-MM-DD HH:mm:ss" /> : null;
    },
  };
}

export function createSystemOrderNumberColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.systemOrderNumber'),
    dataIndex: 'system_order_number',
    render(value) {
      return (
        <Typography.Paragraph
          copyable={{
            text: value,
            icon: <CopyOutlined className="text-[#6eb9ff]" />,
          }}
          className="!mb-0"
        >
          {value}
        </Typography.Paragraph>
      );
    },
  };
}

/**
 * Get all data columns
 */
export function getDataColumns(ctx: ColumnContext): WithdrawColumn[] {
  return [
    createAmountColumn(ctx),
    createFeeColumn(ctx),
    createCreatedAtColumn(ctx),
    createConfirmedAtColumn(ctx),
    createSystemOrderNumberColumn(ctx),
  ];
}
