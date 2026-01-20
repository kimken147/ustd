import { List, useTable } from '@refinedev/antd';
import { Checkbox, Col, Divider, Input, Modal } from 'antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import ContentHeader from 'components/contentHeader';
import useProvider from 'hooks/useProvider';
import useUpdateModal from 'hooks/useUpdateModal';
import { MatchTransactionGroup } from 'interfaces/transactionGroup';
import Enviroment from 'lib/env';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const TransactionGroupList: FC = () => {
  const { t } = useTranslation('providers');
  const { t: tc } = useTranslation();
  const isPaufen = Enviroment.isPaufen;
  const name = isPaufen ? t('transactionGroup.provider') : t('transactionGroup.group');

  const { Select: ProviderSelect } = useProvider();

  const { tableProps, searchFormProps } = useTable<MatchTransactionGroup>({
    resource: 'merchant-transaction-groups',
    syncWithLocation: true,
  });

  const { modalProps, show, Modal: UpdateModal } = useUpdateModal({
    formItems: [
      {
        label: name,
        name: 'provider_id',
        children: <ProviderSelect />,
        rules: [{ required: true }],
      },
      { name: 'merchant_id', hidden: true },
      {
        name: 'personal_enable',
        label: t('transactionGroup.agentLine'),
        children: <Checkbox />,
        valuePropName: 'checked',
        extra: t('transactionGroup.agentLineDescription'),
      },
    ],
  });

  const columnDeps: ColumnDependencies = { t, tc, name, show, UpdateModal };
  const columns = useColumns(columnDeps);

  return (
    <List title={<ContentHeader title={t('titles.moneyInDirectLine')} resource="providers" />}>
      <Helmet>
        <title>{t('titles.moneyInDirectLine')}</title>
      </Helmet>

      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('transactionGroup.merchantName')} name="name_or_username">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />

      <Modal {...modalProps} />
    </List>
  );
};

export default TransactionGroupList;
