import { CloseCircleOutlined, EditOutlined, ReloadOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space, Switch } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createDailyStatusColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, dayEnable, canEdit, mutateUserChannel } = deps;

  if (!dayEnable) return null;

  return {
    dataIndex: 'daily_status',
    title: t('switches.dailyLimitSwitch'),
    render(value, record) {
      return (
        <Switch
          disabled={!canEdit}
          checked={value}
          onChange={value => {
            mutateUserChannel({
              record,
              values: { daily_status: value },
            });
          }}
        />
      );
    },
  };
}

export function createDailyLimitReceiveColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, dayEnable, canEdit, showUpdateModal, mutateUserChannel } = deps;

  if (!dayEnable) return null;

  return {
    title: t('fields.dailyLimitReceiveUsed'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.daily_limit).format('0,0.00')} / ${record.daily_total}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyLimitReceive'),
                id: record.id,
                initialValues: { daily_limit: record.daily_limit },
                filterFormItems: ['daily_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_total: 0 },
                title: t('confirmation.resetDailyUsedReceive'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_limit_null: true },
                title: t('confirmation.resetDailyLimitDefaultReceive'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createDailyLimitPayoutColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, dayEnable, canEdit, showUpdateModal, mutateUserChannel } = deps;

  if (!dayEnable) return null;

  return {
    title: t('fields.dailyLimitPayoutUsed'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.withdraw_daily_limit).format('0,0.00')} / ${record.withdraw_daily_total}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyLimitPayout'),
                id: record.id,
                initialValues: { withdraw_daily_limit: record.withdraw_daily_limit },
                filterFormItems: ['withdraw_daily_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_daily_total: 0 },
                title: t('confirmation.resetDailyUsedPayout'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_withdraw_limit_null: true },
                title: t('confirmation.resetDailyLimitDefaultPayout'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
