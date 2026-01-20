import { Col, Divider, Input, Select } from 'antd';
import { CreateButton, List, useTable } from '@refinedev/antd';
import { useTranslate } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { BankCard } from 'interfaces/bankCard';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns, type ColumnDependencies } from './columns';

const BankCardList: FC = () => {
  const t = useTranslate();
  const title = t('bankCard.titles.list');

  const { Modal, show: showUpdateModal } = useUpdateModal({
    formItems: [
      {
        label: t('bankCard.fields.accountOwner'),
        name: 'bank_card_holder_name',
        children: <Input />,
        rules: [{ required: true }],
      },
      {
        label: t('bankCard.fields.bankAccount'),
        name: 'bank_card_number',
        children: <Input />,
        rules: [{ required: true }],
      },
      {
        label: t('bankCard.fields.bankName'),
        name: 'bank_name',
        children: <Input />,
        rules: [{ required: true }],
      },
    ],
  });

  const { tableProps, searchFormProps } = useTable<BankCard>({
    syncWithLocation: true,
  });

  const columnDeps: ColumnDependencies = { t, showUpdateModal };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={<ContentHeader title={title} />}
        headerButtons={() => <CreateButton>{t('bankCard.buttons.create')}</CreateButton>}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('bankCard.fields.bankAccount')} name="q">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('status')} name="status[]">
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

export default BankCardList;
