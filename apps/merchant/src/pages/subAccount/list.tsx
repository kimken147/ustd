import { Col, Divider, Input } from 'antd';
import { CreateButton, List, useTable } from '@refinedev/antd';
import { useTranslate } from '@refinedev/core';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { SubAccount } from 'interfaces/subAccount';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns, type ColumnDependencies } from './columns';

const SubAccountList: FC = () => {
  const t = useTranslate();
  const title = t('subAccount.titles.list');

  const { tableProps, searchFormProps } = useTable<SubAccount>({
    resource: 'sub-accounts',
    syncWithLocation: true,
  });

  const columnDeps: ColumnDependencies = { t };
  const columns = useColumns(columnDeps);

  return (
    <List
      title={title}
      headerButtons={() => <CreateButton>{t('subAccount.buttons.create')}</CreateButton>}
    >
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('subAccount.query.nameOrAccount')} name="name_or_username">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
    </List>
  );
};

export default SubAccountList;
