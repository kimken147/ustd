import { Col, Divider, Input, Select } from 'antd';
import { List, useTable } from '@refinedev/antd';
import { useCan } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { UserBankCard } from 'interfaces/userBankCard';
import Enviroment from 'lib/env';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const UserBankCardList: FC = () => {
  const { t } = useTranslation();
  const isPaufen = Enviroment.isPaufen;
  const title = isPaufen ? t('bankCard.titles.merchantProviderList') : t('bankCard.titles.merchantList');

  const { data: canDelete } = useCan({ action: '14', resource: 'user-bank-cards' });
  const { Modal } = useUpdateModal();

  const getStatusText = (status: number) => {
    if (status === 1) return t('bankCard.review.wait');
    else if (status === 2) return t('bankCard.review.success');
    else return t('bankCard.review.fail');
  };

  const { tableProps, searchFormProps } = useTable<UserBankCard>({
    syncWithLocation: true,
  });

  const columnDeps: ColumnDependencies = {
    t,
    Modal,
    canDelete: canDelete?.can ?? false,
    getStatusText,
  };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List title={<ContentHeader title={title} resource="withdraws" />}>
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('bankCard.fields.nameOrUsername')} name="name_or_username">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('bankCard.fields.bankCardKeyword')} name="q">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('bankCard.fields.status')} name="status[]">
                <Select
                  mode="multiple"
                  allowClear
                  options={[
                    { label: t('bankCard.review.wait'), value: 1 },
                    { label: t('bankCard.review.success'), value: 2 },
                    { label: t('bankCard.review.fail'), value: 3 },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
      <Modal />
    </>
  );
};

export default UserBankCardList;
