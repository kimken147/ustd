import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import {
  Button,
  Divider,
  Input,
  List,
  Space,
  TextField,
} from '@pankod/refine-antd';
import ContentHeader from 'components/contentHeader';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { User, WhitelistedIp } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const ProviderWhiteList: FC = () => {
  const { t } = useTranslation('providers');
  const { Form, Table, refetch } = useTable<User>({
    resource: 'users',
    filters: [
      {
        field: 'include[]',
        value: 'whitelisted_ips',
        operator: 'eq',
      },
      {
        field: 'role',
        value: 2,
        operator: 'eq',
      },
      {
        field: 'whitelisted_ip_type',
        value: 1,
        operator: 'eq',
      },
    ],
    formItems: [
      {
        label: t('filters.nameOrAccount'),
        name: 'name_or_fuzzy_username',
        children: <Input allowClear />,
      },
      {
        label: t('whiteList.ipv4'),
        name: 'ipv4',
        children: <Input allowClear />,
      },
    ],
  });
  const { show, Modal } = useUpdateModal({
    resource: 'whitelisted-ips',
    formItems: [
      {
        label: t('whiteList.ipv4'),
        name: 'ipv4',
        children: <Input />,
      },
    ],
    onSuccess: () => {
      refetch();
    },
  });
  return (
    <>
      <Helmet>
        <title>{t('titles.whiteList')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader title={t('titles.whiteList')} resource="providers" />
        }
      >
        <Form />
        <Divider />
        <Table>
          <Table.Column title={t('fields.name')} dataIndex={'name'} />
          <Table.Column title={t('fields.username')} dataIndex={'username'} />
          <Table.Column
            title={t('whiteList.ipv4')}
            dataIndex={'whitelisted_ips'}
            render={(value: Array<WhitelistedIp>) => (
              <Space size={'large'}>
                {value.map(list => (
                  <Space key={list.id}>
                    <TextField value={list.ipv4} />
                    <EditOutlined
                      style={{
                        color: '#6eb9ff',
                      }}
                      onClick={() => {
                        show({
                          title: t('whiteList.edit'),
                          id: list.id,
                          resource: 'whitelisted-ips',
                          filterFormItems: ['ipv4'],
                          initialValues: {
                            ipv4: list.ipv4,
                          },
                        });
                      }}
                    />
                    <DeleteOutlined
                      style={{
                        color: '#ff4d4f',
                      }}
                      onClick={() => {
                        Modal.confirm({
                          title: t('whiteList.confirmDelete'),
                          id: list.id,
                          mode: 'delete',
                          values: {},
                          resource: 'whitelisted-ips',
                          onSuccess: () => {
                            refetch();
                          },
                        });
                      }}
                    />
                  </Space>
                ))}
              </Space>
            )}
          />
          <Table.Column<User>
            title={t('operation', {
              ns: 'common',
            })}
            render={(value, record) => {
              return (
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={() => {
                    show({
                      filterFormItems: ['ipv4', 'user_id', 'type'],
                      id: record.id,
                      title: t('whiteList.add'),
                      formValues: {
                        user_id: record.id,
                        type: 1,
                      },
                      mode: 'create',
                    });
                  }}
                >
                  {t('create', {
                    ns: 'common',
                  })}
                </Button>
              );
            }}
          />
        </Table>
        <Modal
          defaultValue={{
            type: 1,
          }}
        />
      </List>
    </>
  );
};

export default ProviderWhiteList;
