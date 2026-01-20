import { CloseCircleOutlined, EditOutlined, ReloadOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space, Switch } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createMonthlyStatusColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, monthEnable, canEdit, mutateUserChannel } = deps;

  if (!monthEnable) return null;

  return {
    dataIndex: 'monthly_status',
    title: t('switches.monthlyLimitSwitch'),
    render(value, record) {
      return (
        <Switch
          disabled={!canEdit}
          checked={value}
          onChange={value => {
            mutateUserChannel({
              record,
              values: { monthly_status: value },
            });
          }}
        />
      );
    },
  };
}

export function createMonthlyLimitReceiveColumn(
  deps: ColumnDependencies
): UserChannelColumn | null {
  const { t, monthEnable, canEdit, showUpdateModal, mutateUserChannel } = deps;

  if (!monthEnable) return null;

  return {
    title: t('fields.monthlyLimitReceiveUsed'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.monthly_limit).format('0,0.00')} / ${record.monthly_total}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyLimitReceive'),
                id: record.id,
                initialValues: { monthly_limit: record.monthly_limit },
                filterFormItems: ['monthly_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_total: 0 },
                title: t('confirmation.resetMonthlyUsedReceive'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_limit_null: true },
                title: t('confirmation.resetMonthlyLimitDefaultReceive'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createMonthlyLimitPayoutColumn(
  deps: ColumnDependencies
): UserChannelColumn | null {
  const { t, monthEnable, canEdit, showUpdateModal, mutateUserChannel } = deps;

  if (!monthEnable) return null;

  return {
    title: t('fields.monthlyLimitPayoutUsed'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.withdraw_monthly_limit).format('0,0.00')} / ${record.withdraw_monthly_total}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyLimitPayout'),
                id: record.id,
                initialValues: { withdraw_monthly_limit: record.withdraw_monthly_limit },
                filterFormItems: ['withdraw_monthly_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_monthly_total: 0 },
                title: t('confirmation.resetMonthlyUsedPayout'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_withdraw_limit_null: true },
                title: t('confirmation.resetMonthlyLimitDefaultPayout'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
