import {
  DeleteOutlined,
  EditOutlined,
  PlusSquareOutlined,
} from '@ant-design/icons';
import {
  Button,
  Divider,
  Input,
  List,
  Space,
  TableColumnProps,
  TextField,
} from '@pankod/refine-antd';
import ContentHeader from 'components/contentHeader';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { User, WhitelistedIp } from 'interfaces/user';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const LoginWhiteList: FC = () => {
  const { t } = useTranslation('permission');
  const { Modal, show } = useUpdateModal({
    formItems: [
      {
        label: t('whitelist.filters.loginWhitelist'),
        name: 'ipv4',
        children: <Input />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        name: 'type',
        hidden: true,
      },
      {
        name: 'user_id',
        hidden: true,
      },
    ],
  });
  const { Form, Table, refetch } = useTable<User>({
    resource: 'users',
    formItems: [
      {
        label: t('whitelist.filters.nameOrLoginAccount'),
        name: 'name_or_fuzzy_username',
        children: <Input />,
      },
      {
        label: t('whitelist.filters.loginWhitelist'),
        name: 'ipv4',
        children: <Input />,
      },
    ],
    filters: [
      {
        field: 'include[]',
        value: 'whitelisted_ips',
        operator: 'eq',
      },
      {
        field: 'role',
        value: 1,
        operator: 'eq',
      },
      {
        field: 'whitelisted_ip_type',
        value: 1,
        operator: 'eq',
      },
    ],
  });

  const columns: TableColumnProps<User>[] = [
    {
      title: t('whitelist.fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('whitelist.fields.loginAccount'),
      dataIndex: 'username',
    },
    {
      title: t('whitelist.fields.loginWhitelist'),
      dataIndex: 'whitelisted_ips',
      render(value: WhitelistedIp[], record, index) {
        return (
          <Space>
            {value?.map(white => (
              <Space key={white.id}>
                <TextField value={white.ipv4} code />
                <Button
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  size="small"
                  onClick={() =>
                    show({
                      title: t('whitelist.actions.editWhitelist'),
                      id: white.id,
                      initialValues: {
                        ipv4: white.ipv4,
                      },
                      onSuccess() {
                        refetch();
                      },
                      resource: 'whitelisted-ips',
                    })
                  }
                />
                <Button
                  icon={
                    <DeleteOutlined
                      style={{
                        color: '#6eb9ff',
                      }}
                    />
                  }
                  onClick={() =>
                    Modal.confirm({
                      id: white.id,
                      resource: 'whitelisted-ips',
                      title: t('whitelist.messages.confirmDelete'),
                      mode: 'delete',
                      onSuccess() {
                        refetch();
                      },
                    })
                  }
                  size="small"
                />
              </Space>
            ))}
          </Space>
        );
      },
    },
    {
      title: t('whitelist.fields.operation'),
      render(value, record, index) {
        return (
          <Button
            icon={<PlusSquareOutlined />}
            onClick={() =>
              show({
                title: t('whitelist.actions.addWhitelist'),
                id: record.id,
                initialValues: {
                  user_id: record.id,
                  type: 1,
                },
                mode: 'create',
                resource: 'whitelisted-ips',
                onSuccess() {
                  refetch();
                },
              })
            }
          >
            {t('whitelist.actions.add')}
          </Button>
        );
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('whitelist.title')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader title={t('whitelist.title')} resource="sub-accounts" />
        }
      >
        <Form />
        <Divider />
        <Table columns={columns} />
      </List>
      <Modal />
    </>
  );
};

export default LoginWhiteList;
