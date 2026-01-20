import { FC, useState } from 'react';
import {
  CheckOutlined,
  StopOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { CreateButton, List, ListButton, useTable } from '@refinedev/antd';
import { Col, Divider, FormItemProps, Input, InputNumber, Select } from 'antd';
import { useApiUrl, useCan } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, Merchant, Tag as TagModel } from '@morgan-ustd/shared';
import useMerchant from 'hooks/useMerchant';
import useSelector from 'hooks/useSelector';
import { useTagEdit } from 'hooks/useTagEdit';
import useUpdateModal from 'hooks/useUpdateModal';
import { useColumns, UpdateMerchantParams, type ColumnDependencies } from './columns';
import StatisticsCard from './StatisticsCard';
import BatchOperationsBar from './BatchOperationsBar';

interface Meta {
  total_balance?: number;
}

export const updateMerchantFormItems: (t: Function) => FormItemProps[] = t => [
  {
    label: t('fields.balanceLimit'),
    name: UpdateMerchantParams.balance_limit,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.type'),
    name: 'type',
    children: (
      <Select
        options={['add', 'minus'].map(type => ({
          label: t(`type.${type}`),
          value: type,
        }))}
      />
    ),
  },
  {
    label: t('wallet.balanceDelta'),
    name: UpdateMerchantParams.balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('wallet.frozenBalanceDelta'),
    name: UpdateMerchantParams.frozen_balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('wallet.withdrawFee'),
    name: 'withdraw_fee',
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.note'),
    name: 'note',
    children: <Input.TextArea />,
  },
  {
    label: t('switches.transactionEnable'),
    name: UpdateMerchantParams.transaction_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
  {
    label: t('switches.agencyWithdrawEnable'),
    name: UpdateMerchantParams.agency_withdraw_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
  {
    label: t('switches.withdrawEnable'),
    name: UpdateMerchantParams.withdraw_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
];

const MerchantList: FC = () => {
  const apiUrl = useApiUrl();
  const { t } = useTranslation('merchant');
  const [selectedKeys, setSelectedKeys] = useState<React.Key[]>([]);

  const { data: canEditWallet } = useCan({
    action: '18',
    resource: 'merchants',
  });
  const { data: canEditProfile } = useCan({
    action: '4',
    resource: 'merchants',
  });

  const { Select: MerchantSelect } = useMerchant({ valueField: 'username' });

  const { selectProps: selectTagProps } = useSelector<TagModel>({
    resource: 'tags',
  });

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: tableData, refetch },
  } = useTable<Merchant>({
    resource: 'merchants',
    syncWithLocation: true,
  });

  const meta = (tableData as any)?.meta as Meta | undefined;

  const {
    modalProps,
    show,
    Modal: UpdateModal,
  } = useUpdateModal({
    formItems: updateMerchantFormItems(t),
    transferFormValues(record) {
      const values = { ...record };
      if (values.balance_delta) {
        values.balance_delta =
          values.type === 'add' ? values.balance_delta : -values.balance_delta;
      }
      if (values.frozen_balance_delta) {
        values.frozen_balance_delta =
          values.type === 'add'
            ? values.frozen_balance_delta
            : -values.frozen_balance_delta;
      }
      return values;
    },
  });

  const { showTagModal, tagModal } = useTagEdit({
    selectTagProps,
    resource: 'merchants',
  });

  const columnDeps: ColumnDependencies = {
    t,
    canEditWallet: canEditWallet?.can ?? false,
    canEditProfile: canEditProfile?.can ?? false,
    show,
    showTagModal,
    Modal: UpdateModal,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('name')}管理</title>
      </Helmet>
      <List
        headerButtons={() => (
          <>
            <ListButton icon={<StopOutlined />} resource="merchants/banned-list">
              {t('titles.bannedList')}
            </ListButton>
            <ListButton icon={<CheckOutlined />} resource="merchants/white-list">
              {t('titles.loginWhiteList')}
            </ListButton>
            <ListButton icon={<CheckOutlined />} resource="merchants/api-white-list">
              {t('titles.apiWhiteList')}
            </ListButton>
            <ListButton icon={<WalletOutlined />} resource="merchants/wallet-histories">
              {t('titles.walletHistory')}
            </ListButton>
            <CreateButton>{t('actions.create')}</CreateButton>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('fields.nameOrAccount')}
                name="merchant_name_or_username[]"
              >
                <MerchantSelect mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item
                label={t('fields.agentNameOrAccount')}
                name="agent_name_or_username"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('fields.tag')} name="tag_ids[]">
                <Select {...selectTagProps} mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <StatisticsCard totalBalance={meta?.total_balance} label={t('wallet.totalBalance')} />
        <Divider />

        <BatchOperationsBar
          selectedKeys={selectedKeys}
          canEditProfile={canEditProfile?.can ?? false}
          apiUrl={apiUrl}
          t={t}
          show={show}
          refetch={refetch}
          onClearSelection={() => setSelectedKeys([])}
        />

        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          rowSelection={
            canEditProfile?.can
              ? {
                  selectedRowKeys: selectedKeys,
                  onChange: keys => setSelectedKeys(keys),
                  preserveSelectedRowKeys: true,
                }
              : undefined
          }
        />
        <UpdateModal
          {...modalProps}
          defaultValue={{
            [UpdateMerchantParams.balance_limit]: 0,
            [UpdateMerchantParams.balance_delta]: 0,
            [UpdateMerchantParams.type]: 'add',
          }}
        />
      </List>
      {tagModal}
    </>
  );
};

export default MerchantList;
