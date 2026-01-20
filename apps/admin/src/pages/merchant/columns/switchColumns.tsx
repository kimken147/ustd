import { Switch } from 'antd';
import type { ColumnDependencies, MerchantColumn } from './types';
import { UpdateMerchantParams } from './types';

export function createStatusColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, Modal } = deps;

  return {
    title: t('switches.accountStatus'),
    dataIndex: 'status',
    render(value, record) {
      return (
        <Switch
          disabled={!canEditProfile}
          checked={value}
          onChange={checked => {
            Modal.confirm({
              title: t('confirmation.accountStatus'),
              id: record.id,
              values: {
                [UpdateMerchantParams.status]: +checked,
              },
            });
          }}
        />
      );
    },
  };
}

export function createTransactionEnableColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, Modal } = deps;

  return {
    title: t('switches.transactionEnable'),
    dataIndex: 'transaction_enable',
    render(value, record) {
      return (
        <Switch
          disabled={!canEditProfile}
          checked={value}
          onChange={checked => {
            Modal.confirm({
              title: t('confirmation.transactionEnable'),
              id: record.id,
              values: {
                [UpdateMerchantParams.transaction_enable]: +checked,
              },
            });
          }}
        />
      );
    },
  };
}

export function createWithdrawEnableColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, Modal } = deps;

  return {
    title: t('switches.withdrawEnable'),
    dataIndex: 'withdraw_enable',
    render(value, record) {
      return (
        <Switch
          disabled={!canEditProfile}
          checked={value}
          onChange={checked => {
            Modal.confirm({
              title: t('confirmation.withdrawEnable'),
              id: record.id,
              values: {
                [UpdateMerchantParams.withdraw_enable]: +checked,
              },
            });
          }}
        />
      );
    },
  };
}

export function createAgencyWithdrawEnableColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, Modal } = deps;

  return {
    title: t('switches.agencyWithdrawEnable'),
    dataIndex: 'agency_withdraw_enable',
    render(value, record) {
      return (
        <Switch
          disabled={!canEditProfile}
          checked={value}
          onChange={checked => {
            Modal.confirm({
              title: t('confirmation.agencyWithdrawEnable'),
              id: record.id,
              values: {
                [UpdateMerchantParams.agency_withdraw_enable]: +checked,
              },
            });
          }}
        />
      );
    },
  };
}
