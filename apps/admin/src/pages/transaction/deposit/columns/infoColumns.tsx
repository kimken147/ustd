import { ShowButton } from '@refinedev/antd';
import { Space } from 'antd';
import type { Merchant } from '@morgan-ustd/shared';
import type { Provider } from 'interfaces/provider';
import type { ColumnDependencies, DepositColumn } from './types';

export function createTypeColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.providerDepositType'),
    dataIndex: 'type',
    render(value) {
      return value === 3 ? t('types.generalDeposit') : t('types.paufenDeposit');
    },
  };
}

export function createProviderColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.payoutPartyInfo'),
    dataIndex: 'provider',
    render(value: Provider) {
      return (
        <ShowButton icon={null} resource="providers" recordItemId={value.id}>
          {value.name}
        </ShowButton>
      );
    },
  };
}

export function createAmountColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.amount'),
    dataIndex: 'amount',
  };
}

export function createCollectionPartyColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.collectionPartyInfo'),
    render(_, record) {
      const { bank_card_holder_name, bank_card_number, bank_name } =
        record.from_channel_account;
      return `${bank_card_holder_name} - ${bank_card_number} - ${bank_name}`;
    },
  };
}

export function createStatusColumn(deps: ColumnDependencies): DepositColumn {
  const { t, getStatusText } = deps;

  return {
    title: t('fields.orderStatus'),
    dataIndex: 'status',
    render(value) {
      return getStatusText(value);
    },
  };
}

export function createMerchantColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.userName'),
    dataIndex: 'merchant',
    render(value: Merchant) {
      if (!value) return null;

      return (
        <Space>
          <div className="w-5 h-5 relative">
            <img
              src={value.role !== 3 ? '/provider-icon.png' : '/merchant-icon.png'}
              alt=""
              className="object-contain"
            />
          </div>
          <ShowButton
            icon={null}
            recordItemId={value.id}
            resource={value.role !== 3 ? 'providers' : 'merchants'}
          >
            {value.name}
          </ShowButton>
        </Space>
      );
    },
  };
}
