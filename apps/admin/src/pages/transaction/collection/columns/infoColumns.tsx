import { Popover, Space } from 'antd';
import { ShowButton, TextField } from '@refinedev/antd';
import { InfoCircleOutlined } from '@ant-design/icons';
import type { Thirdchannel } from '@morgan-ustd/shared';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createProviderColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, isPaufen, groupLabel } = deps;

  return {
    title: t('fields.providerAccountTitle', { groupLabel }),
    dataIndex: ['provider', 'name'],
    render(value, record) {
      return isPaufen && record.provider ? (
        <ShowButton recordItemId={record.provider?.id} icon={null} resource="providers">
          {value}
        </ShowButton>
      ) : (
        (value ?? '-')
      );
    },
  };
}

export function createThirdChannelColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.thirdPartyAccount'),
    dataIndex: 'thirdchannel',
    render(value: Thirdchannel) {
      return value ? `${value.name}(${value.merchant_id ?? ''})` : '';
    },
  };
}

export function createAccountNumberColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.accountNumber'),
    dataIndex: 'provider_channel_account_hash_id',
    render(value, record) {
      if (!value) return null;
      return (
        <Space>
          <TextField value={value} />
          <Popover
            trigger={'click'}
            content={
              <Space direction="vertical">
                <TextField
                  value={t('fields.collectionAccountWithValue', {
                    account: record.provider_account,
                  })}
                />
                {record.provider_account_note ? (
                  <TextField
                    value={t('info.note', {
                      note: record.provider_account_note,
                    })}
                  />
                ) : null}
              </Space>
            }
          >
            <InfoCircleOutlined className="text-[#6eb9ff]" />
          </Popover>
        </Space>
      );
    },
  };
}

export function createMerchantColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.merchantName'),
    render(_value, record) {
      return (
        <ShowButton recordItemId={record.merchant.id} resource="merchants" icon={false}>
          {record.merchant.name}
        </ShowButton>
      );
    },
  };
}
