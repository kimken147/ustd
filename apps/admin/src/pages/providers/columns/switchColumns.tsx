import { Switch } from 'antd';
import { UpdateProviderParams, type ColumnDependencies, type ProviderColumn } from './types';

export function createStatusColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('filters.accountStatus'),
    dataIndex: 'status',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.accountStatus'),
              id: record.id,
              values: { [UpdateProviderParams.status]: +checked },
            });
          }}
        />
      );
    },
  };
}

export function createGoogle2faColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('fields.google2fa'),
    dataIndex: 'google2fa_enable',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.google2fa'),
              id: record.id,
              values: { [UpdateProviderParams.google2fa_enable]: +checked },
            });
          }}
        />
      );
    },
  };
}

export function createTransactionEnableColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('switches.transactionEnable'),
    dataIndex: 'transaction_enable',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.transactionEnable'),
              id: record.id,
              values: { [UpdateProviderParams.transaction_enable]: +checked },
            });
          }}
        />
      );
    },
  };
}

export function createDepositEnableColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('switches.depositEnable'),
    dataIndex: 'deposit_enable',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.depositEnable'),
              id: record.id,
              values: { [UpdateProviderParams.deposit_enable]: +checked },
            });
          }}
        />
      );
    },
  };
}

export function createPaufenDepositEnableColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('switches.paufenDepositEnable'),
    dataIndex: 'paufen_deposit_enable',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.paufenDepositEnable'),
              id: record.id,
              values: { [UpdateProviderParams.paufen_deposit_enable]: +checked },
            });
          }}
        />
      );
    },
  };
}

export function createAgentEnableColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, UpdateModal } = deps;

  return {
    title: t('fields.agentEnable'),
    dataIndex: 'agent_enable',
    render(value, record) {
      return (
        <Switch
          checked={value}
          onChange={checked => {
            UpdateModal.confirm({
              title: t('confirmation.agentEnable', {
                agentEnable: t('fields.agentEnable'),
              }),
              id: record.id,
              values: { [UpdateProviderParams.agent_enable]: +checked },
            });
          }}
        />
      );
    },
  };
}
