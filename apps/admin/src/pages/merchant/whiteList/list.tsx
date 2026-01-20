import { Col, Divider, Input } from 'antd';
import { List, useTable } from '@refinedev/antd';
import ContentHeader from 'components/contentHeader';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { User } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const MerchantWhiteList: FC = () => {
  const { t } = useTranslation('merchant');

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
        { field: 'role', value: 3, operator: 'eq' },
        { field: 'whitelisted_ip_type', value: 1, operator: 'eq' },
      ],
    },
  });

  const { show, Modal } = useUpdateModal({
    resource: 'whitelisted-ips',
    formItems: [
      {
        label: t('whiteList.ipv4'),
        name: 'ipv4',
        children: <Input />,
        rules: [{ required: true, message: t('validation.required') }],
      },
    ],
    onSuccess: () => refetch(),
  });

  const columnDeps: ColumnDependencies = { t, show, Modal, refetch };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('titles.loginWhiteList')}</title>
      </Helmet>
      <List title={<ContentHeader title={t('titles.loginWhiteList')} resource="merchants" />}>
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={12}>
              <ListPageLayout.Filter.Item label={t('fields.merchantOrAccount')} name="name_or_fuzzy_username">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={12}>
              <ListPageLayout.Filter.Item label={t('whiteList.ipv4')} name="ipv4">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
      <Modal defaultValue={{ type: 1 }} />
    </>
  );
};

export default MerchantWhiteList;
