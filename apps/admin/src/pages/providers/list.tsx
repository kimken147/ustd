import { FC } from 'react';
import {
  LoginOutlined,
  PlusSquareOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { CreateButton, List, ListButton, useTable } from '@refinedev/antd';
import { Col, Divider, FormItemProps, Input, InputNumber, Modal, Select } from 'antd';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, Tag as TagModel } from '@morgan-ustd/shared';
import useSelector from 'hooks/useSelector';
import { useTagEdit } from 'hooks/useTagEdit';
import useUpdateModal from 'hooks/useUpdateModal';
import { Provider } from 'interfaces/provider';
import { getStatusOptions } from 'lib/status';
import { useColumns, UpdateProviderParams, type ColumnDependencies } from './columns';

export const updateMerchantFormItems: (t: Function) => FormItemProps[] = t => [
  {
    label: t('fields.balanceLimit'),
    name: UpdateProviderParams.balance_limit,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('type', { ns: 'common' }),
    name: 'type',
    children: (
      <Select
        options={['add', 'minus'].map(type => ({
          label: type === 'add' ? t('add', { ns: 'common' }) : t('minus', { ns: 'common' }),
          value: type,
        }))}
      />
    ),
  },
  {
    label: t('fields.totalBalance'),
    name: UpdateProviderParams.balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.frozenBalance'),
    name: UpdateProviderParams.frozen_balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('wallet.withdrawFee', { ns: 'common' }),
    name: 'withdraw_fee',
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.profit'),
    name: UpdateProviderParams.profit_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('note', { ns: 'common' }),
    name: 'note',
    children: <Input.TextArea />,
  },
];

const ProvidersList: FC = () => {
  const { t } = useTranslation('providers');

  const { Select: ProviderSelect } = useSelector<Provider>({
    resource: 'providers',
    valueField: 'name',
  });

  const { selectProps: selectTagProps } = useSelector<TagModel>({
    resource: 'tags',
  });

  const { tableProps, searchFormProps } = useTable<Provider>({
    resource: 'providers',
    syncWithLocation: true,
  });

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
      if (values.profit_delta) {
        values.profit_delta =
          values.type === 'add' ? values.profit_delta : -values.profit_delta;
      }
      return values;
    },
  });

  const { showTagModal, tagModal } = useTagEdit({
    selectTagProps,
    resource: 'providers',
  });

  const columnDeps: ColumnDependencies = {
    t,
    show,
    showTagModal,
    UpdateModal,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('titles.list')}</title>
      </Helmet>
      <List
        title={t('titles.list')}
        headerButtons={() => (
          <>
            <ListButton resource="white-list" icon={<LoginOutlined />}>
              {t('titles.whiteList')}
            </ListButton>
            <ListButton resource="wallet-histories" icon={<WalletOutlined />}>
              {t('titles.balanceHistory')}
            </ListButton>
            <ListButton resource="merchant-transaction-groups">
              {t('titles.moneyInDirectLine')}
            </ListButton>
            <ListButton resource="merchant-matching-deposit-groups">
              {t('titles.moneyOutDirectLine')}
            </ListButton>
            <CreateButton icon={<PlusSquareOutlined />}>
              {t('titles.create')}
            </CreateButton>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('filters.nameOrAccount')}
                name="provider_name_or_username"
              >
                <ProviderSelect allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('filters.agentOrAccount')}
                name="agent_name_or_username"
              >
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('filters.accountStatus')}
                name="status"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.transactionEnable')}
                name="transaction_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.tag')} name="tag_ids[]">
                <Select {...selectTagProps} mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.google2faEnable')}
                name="google2fa_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.depositEnable')}
                name="deposit_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.withdrawEnable')}
                name="withdraw_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.verifyMoneyIn')}
                name="cancel_order_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.paufenDepositEnable')}
                name="paufen_deposit_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.withdrawProfit')}
                name="withdraw_profit_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item
                label={t('switches.agentEnable')}
                name="agent_enable"
              >
                <Select options={getStatusOptions()} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} />
        <Modal {...modalProps} />
      </List>
      {tagModal}
    </>
  );
};

export default ProvidersList;
