import {
  CreateButton,
  DeleteButton,
  List,
  Table,
  TableColumnProps,
} from '@refinedev/antd';
import dayjs from 'dayjs';
import useTable from 'hooks/useTable';
import { Tag, Format } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const TagList: FC = () => {
  const { t } = useTranslation();
  const { tableProps } = useTable({
    resource: 'tags',
  });

  const columns: TableColumnProps<Tag>[] = [
    {
      title: t('tagsPage.fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('createAt'),
      dataIndex: 'created_at',
      render(value, record, index) {
        return dayjs(value).format(Format);
      },
    },
    {
      title: t('operation'),
      dataIndex: 'id',
      render: id => {
        return <DeleteButton recordItemId={id} danger></DeleteButton>;
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('tagsPage.title')}</title>
      </Helmet>
      <List
        headerButtons={
          <>
            <CreateButton></CreateButton>
          </>
        }
      >
        <Table {...tableProps} columns={columns}></Table>
      </List>
    </>
  );
};

export default TagList;
