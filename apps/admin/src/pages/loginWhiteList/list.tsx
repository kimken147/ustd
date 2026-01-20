import { List, useTable } from '@refinedev/antd';
import { Col, Divider, Input } from 'antd';
import ContentHeader from 'components/contentHeader';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { User } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const LoginWhiteList: FC = () => {
  const { t } = useTranslation('permission');

  const { Modal, show } = useUpdateModal({
    formItems: [
      {
        label: t('whitelist.filters.loginWhitelist'),
        name: 'ipv4',
        children: <Input />,
        rules: [{ required: true }],
      },
      { name: 'type', hidden: true },
      { name: 'user_id', hidden: true },
    ],
  });

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<User>({
    resource: 'users',
    syncWithLocation: true,
    filters: {
      permanent: [
        { field: 'include[]', value: 'whitelisted_ips', operator: 'eq' },
        { field: 'role', value: 1, operator: 'eq' },
        { field: 'whitelisted_ip_type', value: 1, operator: 'eq' },
      ],
    },
  });

  const columnDeps: ColumnDependencies = { t, show, Modal, refetch };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('whitelist.title')}</title>
      </Helmet>
      <List title={<ContentHeader title={t('whitelist.title')} resource="sub-accounts" />}>
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('whitelist.filters.nameOrLoginAccount')} name="name_or_fuzzy_username">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('whitelist.filters.loginWhitelist')} name="ipv4">
                <Input allowClear />
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

export default LoginWhiteList;
