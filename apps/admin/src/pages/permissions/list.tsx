import { EditOutlined } from '@ant-design/icons';
import {
  CreateButton,
  DateField,
  List,
  ListButton,
  ShowButton,
  TextField,
  useModal,
} from '@refinedev/antd';
import {
  Divider,
  Input,
  Modal,
  Space,
  TableColumnProps,
  Typography,
} from 'antd';
import Badge from 'components/badge';
import PermissionCheckGroup from 'components/permissionCheckGroup';
import usePermission from 'hooks/usePermission';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Permission } from 'interfaces/permission';
import { SubAccount } from 'interfaces/subAccount';
import { Format } from '@morgan-ustd/shared';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

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
  const { Form, Table, refetch } = useTable<SubAccount>({
    resource: 'sub-accounts',
    formItems: [
      {
        label: t('list.filters.subAccountNameOrLogin'),
        name: 'name_or_username',
        children: <Input />,
      },
      {
        label: t('list.filters.permissions'),
        name: 'permissions[]',
        children: <PermissionSelect mode="multiple" />,
      },
    ],
    hasPagination: false,
  });
  const columns: TableColumnProps<SubAccount>[] = [
    {
      title: t('list.fields.subAccountName'),
      dataIndex: 'name',
      render(value, record, index) {
        return (
          <ShowButton icon={null} recordItemId={record.id}>
            {value}
          </ShowButton>
        );
      },
    },
    {
      title: t('list.fields.accountStatus'),
      dataIndex: 'status',
      render(value, record, index) {
        const color = value === 1 ? '#16a34a' : '#ff4d4f';
        const text =
          value === 1 ? t('list.fields.enabled') : t('list.fields.disabled');
        return <Badge color={color} text={text} />;
      },
    },
    {
      title: t('list.fields.permissionSettings'),
      dataIndex: 'permissions',
      render(value: Permission[], record, index) {
        return (
          <Space wrap>
            {value
              .filter(per => !filterIds.includes(per.id))
              .map(permission => (
                <TextField key={permission.id} value={permission.name} code />
              ))}
            <EditOutlined
              style={{
                color: '#6eb9ff',
              }}
              onClick={() => {
                setUserId(record.id);
                setSelectedIds(record.permissions.map(per => per.id));
                show();
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('list.fields.lastLoginTime'),
      render(value, record, index) {
        return (
          <Space>
            {record.last_login_at ? (
              <DateField value={record.last_login_at} format={Format} />
            ) : (
              t('list.fields.none')
            )}
            /
            <TextField
              value={record.last_login_ipv4 || t('list.fields.none')}
            />
          </Space>
        );
      },
    },
  ];
  return (
    <>
      <Helmet>
        <title>{t('list.title')}</title>
      </Helmet>
      <List
        title={t('list.title')}
        headerButtons={() => (
          <>
            <ListButton resourceNameOrRouteName="login-white-list">
              {t('list.buttons.loginWhitelist')}
            </ListButton>
            <CreateButton>{t('list.buttons.createSubAccount')}</CreateButton>
          </>
        )}
      >
        <Form />
        <Divider />
        <Typography.Title level={4}>
          {t('list.subAccountList')}
        </Typography.Title>
        <Table className="mt-4" columns={columns} />
      </List>
      <Modal
        {...modalProps}
        destroyOnClose
        onOk={() =>
          UpdateModal.confirm({
            title: t('list.messages.confirmModifyPermission'),
            id: userId,
            values: {
              id: userId,
              permissions: selectedIds,
            },
            onSuccess() {
              close();
              refetch();
            },
          })
        }
      >
        <PermissionCheckGroup
          defaultIds={selectedIds}
          onChange={ids => setSelectedIds(ids)}
        />
      </Modal>
    </>
  );
};

export default PermissionList;
