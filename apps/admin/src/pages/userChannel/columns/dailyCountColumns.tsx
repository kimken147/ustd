import { CloseCircleOutlined, EditOutlined, ReloadOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createDailyReceiveAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.dailyReceiveAmountAndCount'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.daily_total).format('0,0.00')} / ${record.daily_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyReceiveAmountAndCount'),
                id: record.id,
                initialValues: {
                  daily_total: record.daily_total,
                  daily_transaction_count_total: record.daily_transaction_count_total,
                },
                filterFormItems: ['daily_total', 'daily_transaction_count_total'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_total: 0, daily_transaction_count_total: 0 },
                title: t('confirmation.resetDailyReceiveAmountAndCount'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createDailyPayoutAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.dailyPayoutAmountAndCount'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.withdraw_daily_total).format('0,0.00')} / ${record.withdraw_daily_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyPayoutAmountAndCount'),
                id: record.id,
                initialValues: {
                  withdraw_daily_total: record.withdraw_daily_total,
                  withdraw_daily_transaction_count_total: record.withdraw_daily_transaction_count_total,
                },
                filterFormItems: ['withdraw_daily_total', 'withdraw_daily_transaction_count_total'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_daily_total: 0, withdraw_daily_transaction_count_total: 0 },
                title: t('confirmation.resetDailyPayoutAmountAndCount'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createDailyReceiveCountLimitColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.dailyReceiveCountLimit'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${record.daily_transaction_count_limit ?? '-'} / ${record.daily_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyReceiveCountLimit'),
                id: record.id,
                initialValues: { daily_transaction_count_limit: record.daily_transaction_count_limit },
                filterFormItems: ['daily_transaction_count_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_transaction_count_total: 0 },
                title: t('confirmation.resetDailyReceiveCount'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { daily_transaction_count_limit: null },
                title: t('confirmation.resetDailyReceiveCountDefault'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createDailyPayoutCountLimitColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.dailyPayoutCountLimit'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${record.withdraw_daily_transaction_count_limit ?? '-'} / ${record.withdraw_daily_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editDailyPayoutCountLimit'),
                id: record.id,
                initialValues: { withdraw_daily_transaction_count_limit: record.withdraw_daily_transaction_count_limit },
                filterFormItems: ['withdraw_daily_transaction_count_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_daily_transaction_count_total: 0 },
                title: t('confirmation.resetDailyPayoutCount'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_daily_transaction_count_limit: null },
                title: t('confirmation.resetDailyPayoutCountDefault'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
