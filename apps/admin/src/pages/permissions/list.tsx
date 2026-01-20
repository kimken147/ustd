import { CreateButton, List, ListButton, useModal, useTable } from '@refinedev/antd';
import { Col, Divider, Input, Modal, Typography } from 'antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import PermissionCheckGroup from 'components/permissionCheckGroup';
import usePermission from 'hooks/usePermission';
import useUpdateModal from 'hooks/useUpdateModal';
import { SubAccount } from 'interfaces/subAccount';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const PermissionList: FC = () => {
  const { t } = useTranslation('permission');
  const { Select: PermissionSelect, filterIds } = usePermission();

  const [userId, setUserId] = useState<number>(0);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const { modalProps, show, close } = useModal({
    modalProps: {
      title: t('list.modal.title'),
      okText: t('list.modal.submit'),
      cancelText: t('list.modal.cancel'),
    },
  });

  const { Modal: UpdateModal } = useUpdateModal();

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<SubAccount>({
    resource: 'sub-accounts',
    syncWithLocation: true,
    pagination: { mode: 'off' },
  });

  const onEditPermission = (record: SubAccount) => {
    setUserId(record.id);
    setSelectedIds(record.permissions.map(per => per.id));
    show();
  };

  const columnDeps: ColumnDependencies = { t, filterIds, onEditPermission };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('list.title')}</title>
      </Helmet>
      <List
        title={t('list.title')}
        headerButtons={() => (
          <>
            <ListButton resource="login-white-list">{t('list.buttons.loginWhitelist')}</ListButton>
            <CreateButton>{t('list.buttons.createSubAccount')}</CreateButton>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('list.filters.subAccountNameOrLogin')} name="name_or_username">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('list.filters.permissions')} name="permissions[]">
                <PermissionSelect mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <Typography.Title level={4}>{t('list.subAccountList')}</Typography.Title>
        <ListPageLayout.Table className="mt-4" {...tableProps} columns={columns} rowKey="id" />
      </List>

      <Modal
        {...modalProps}
        destroyOnClose
        onOk={() =>
          UpdateModal.confirm({
            title: t('list.messages.confirmModifyPermission'),
            id: userId,
            values: { id: userId, permissions: selectedIds },
            onSuccess() {
              close();
              refetch();
            },
          })
        }
      >
        <PermissionCheckGroup defaultIds={selectedIds} onChange={ids => setSelectedIds(ids)} />
      </Modal>
    </>
  );
};

export default PermissionList;
