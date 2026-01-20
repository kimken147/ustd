/**
 * Bank columns for PayForAnother list
 * - Bank name
 * - Province
 * - City
 * - Card number
 * - Card holder
 */
import { Space, Button } from 'antd';
import { TextField } from '@refinedev/antd';
import { StopOutlined, RedoOutlined } from '@ant-design/icons';
import { Gray, Red } from '@morgan-ustd/shared';
import HiddenText from 'components/hiddenText';
import type { ColumnContext, WithdrawColumn } from './types';

export function createBankNameColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.bankName'),
    dataIndex: 'bank_name',
    responsive: ['md', 'lg', 'xl', 'xxl'] as const,
  };
}

export function createProvinceColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.province'),
    dataIndex: 'bank_province',
    responsive: ['xl', 'xxl'] as const,
  };
}

export function createCityColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.city'),
    dataIndex: 'bank_city',
    responsive: ['xl', 'xxl'] as const,
  };
}

export function createCardNumberColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, profile, WithdrawStatus } = ctx;

  return {
    title: t('fields.cardNumber'),
    dataIndex: 'bank_card_number',
    responsive: ['md', 'lg', 'xl', 'xxl'] as const,
    render(value, record) {
      let show = false;
      if (
        record.locked &&
        record.locked_by?.id === profile?.id &&
        [WithdrawStatus.审核中, WithdrawStatus.等待付款].includes(record.status)
      ) {
        show = true;
      }
      return show ? (
        value
      ) : (
        <HiddenText key={record.id} text={value} status={record.status} />
      );
    },
  };
}

export function createCardHolderColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, canEdit, apiUrl, showUpdateModal, mutateAsync, refetch, meta } = ctx;

  return {
    title: t('fields.cardHolder'),
    dataIndex: 'bank_card_holder_name',
    responsive: ['sm', 'md', 'lg', 'xl', 'xxl'] as const,
    render(value, record) {
      const isBanned = meta?.banned_realnames?.includes(value);
      return value ? (
        <Space>
          <TextField value={value} delete={isBanned} />
          {isBanned ? (
            <RedoOutlined
              style={{ color: canEdit ? Red : Gray }}
              onClick={async () => {
                if (!canEdit) return;
                await mutateAsync({
                  url: `${apiUrl}/banned/realname/${value}`,
                  method: 'delete',
                  values: { realname: value, type: 2 },
                });
                refetch();
              }}
            />
          ) : (
            <Button
              disabled={!canEdit}
              icon={<StopOutlined style={{ color: canEdit ? Red : Gray }} />}
              onClick={() => {
                showUpdateModal({
                  title: t('actions.blockRealName'),
                  id: 0,
                  filterFormItems: ['note', 'realname', 'type'],
                  initialValues: { realname: value, type: 2 },
                  customMutateConfig: {
                    url: `${apiUrl}/banned/realname`,
                    method: 'post',
                  },
                  onSuccess() {
                    refetch();
                  },
                });
              }}
            />
          )}
        </Space>
      ) : null;
    },
  };
}

/**
 * Get all bank columns
 */
export function getBankColumns(ctx: ColumnContext): WithdrawColumn[] {
  return [
    createBankNameColumn(ctx),
    createProvinceColumn(ctx),
    createCityColumn(ctx),
    createCardNumberColumn(ctx),
    createCardHolderColumn(ctx),
  ];
}
