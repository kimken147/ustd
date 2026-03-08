import { CloseCircleOutlined, EditOutlined, ReloadOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createMonthlyReceiveAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.monthlyReceiveAmountAndCount'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.monthly_total).format('0,0.00')} / ${record.monthly_transaction_count_total ?? 0} 笔`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyReceiveAmountAndCount'),
                id: record.id,
                initialValues: {
                  monthly_total: record.monthly_total,
                  monthly_transaction_count_total: record.monthly_transaction_count_total,
                },
                filterFormItems: ['monthly_total', 'monthly_transaction_count_total'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_total: 0, monthly_transaction_count_total: 0 },
                title: t('confirmation.resetMonthlyReceiveAmountAndCount'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createMonthlyPayoutAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.monthlyPayoutAmountAndCount'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${numeral(record.withdraw_monthly_total).format('0,0.00')} / ${record.withdraw_monthly_transaction_count_total ?? 0} 笔`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyPayoutAmountAndCount'),
                id: record.id,
                initialValues: {
                  withdraw_monthly_total: record.withdraw_monthly_total,
                  withdraw_monthly_transaction_count_total: record.withdraw_monthly_transaction_count_total,
                },
                filterFormItems: ['withdraw_monthly_total', 'withdraw_monthly_transaction_count_total'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_monthly_total: 0, withdraw_monthly_transaction_count_total: 0 },
                title: t('confirmation.resetMonthlyPayoutAmountAndCount'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createMonthlyReceiveCountLimitColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.monthlyReceiveCountLimit'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${record.monthly_transaction_count_limit ?? '-'} / ${record.monthly_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyReceiveCountLimit'),
                id: record.id,
                initialValues: { monthly_transaction_count_limit: record.monthly_transaction_count_limit },
                filterFormItems: ['monthly_transaction_count_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_transaction_count_total: 0 },
                title: t('confirmation.resetMonthlyReceiveCount'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { monthly_transaction_count_limit: null },
                title: t('confirmation.resetMonthlyReceiveCountDefault'),
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createMonthlyPayoutCountLimitColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal, mutateUserChannel } = deps;

  return {
    title: t('fields.monthlyPayoutCountLimit'),
    render(_, record) {
      return (
        <Space>
          <TextField
            value={`${record.withdraw_monthly_transaction_count_limit ?? '-'} / ${record.withdraw_monthly_transaction_count_total ?? 0}`}
          />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editMonthlyPayoutCountLimit'),
                id: record.id,
                initialValues: { withdraw_monthly_transaction_count_limit: record.withdraw_monthly_transaction_count_limit },
                filterFormItems: ['withdraw_monthly_transaction_count_limit'],
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<ReloadOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_monthly_transaction_count_total: 0 },
                title: t('confirmation.resetMonthlyPayoutCount'),
              });
            }}
          />
          <Button
            disabled={!canEdit}
            icon={<CloseCircleOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
            onClick={() => {
              mutateUserChannel({
                record,
                values: { withdraw_monthly_transaction_count_limit: null },
                title: t('confirmation.resetMonthlyPayoutCountDefault'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
