import { CreateButton, List, useTable } from '@refinedev/antd';
import { ListPageLayout, Tag } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const TagList: FC = () => {
  const { t } = useTranslation();

  const { tableProps } = useTable<Tag>({
    resource: 'tags',
    syncWithLocation: true,
  });

  const columnDeps: ColumnDependencies = { t };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('tagsPage.title')}</title>
      </Helmet>
      <List headerButtons={<CreateButton />}>
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
    </>
  );
};

export default TagList;
