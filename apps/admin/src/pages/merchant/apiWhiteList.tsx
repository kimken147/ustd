import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import {
  Button,
  Col,
  Divider,
  Input,
  List,
  Row,
  Space,
  TextField,
  Table,
} from '@refinedev/antd';
import ContentHeader from 'components/contentHeader';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { User, WhitelistedIp } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const MerchantApiWhiteList: FC = () => {
  const { t } = useTranslation('merchant');

  const {
    Form,
    Table: TableComponent,
    refetch,
  } = useTable<User>({
    resource: 'users',
    formItems: [
      {
        label: t('fields.merchantOrAccount'),
        name: 'name_or_fuzzy_username',
        children: <Input />,
      },
      {
        label: t('apiWhiteList.apiWhiteList'),
        name: 'ipv4',
        children: <Input />,
      },
    ],
    filters: [
      { field: 'include[]', value: 'whitelisted_ips', operator: 'eq' },
      { field: 'role', value: 3, operator: 'eq' },
      { field: 'whitelisted_ip_type', value: 2, operator: 'eq' },
    ],
  });

  const { show, Modal } = useUpdateModal({
    resource: 'whitelisted-ips',
    formItems: [
      {
        label: t('apiWhiteList.apiWhiteList'),
        name: 'ipv4',
        children: <Input />,
        rules: [{ required: true }],
      },
    ],
    onSuccess: () => refetch(),
  });

  return (
    <>
      <Helmet>
        <title>{t('titles.apiWhiteList')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader
            title={t('titles.apiWhiteList')}
            resource="merchants"
          />
        }
      >
        <Form />
        <Divider />
        <TableComponent>
          <Table.Column title={t('fields.name')} dataIndex={'name'} />
          <Table.Column title={t('fields.username')} dataIndex={'username'} />
          <Table.Column<User>
            title={t('apiWhiteList.title')}
            dataIndex={'whitelisted_ips'}
            render={(value: WhitelistedIp[]) => (
              <Row gutter={[16, 16]}>
                {value.map(list => (
                  <Col key={list.id} xs={24} md={12} lg={6}>
                    <Space>
                      <TextField
                        value={list.ipv4}
                        className="bg-stone-300 p-2"
                      />
                      <EditOutlined
                        style={{ color: '#6eb9ff' }}
                        onClick={() => {
                          show({
                            title: t('apiWhiteList.edit'),
                            id: list.id,
                            resource: 'whitelisted-ips',
                            filterFormItems: ['ipv4'],
                            initialValues: { ipv4: list.ipv4 },
                          });
                        }}
                      />
                      <DeleteOutlined
                        style={{ color: '#ff4d4f' }}
                        onClick={() =>
                          Modal.confirm({
                            title: t('apiWhiteList.confirmDelete'),
                            id: list.id,
                            mode: 'delete',
                            resource: 'whitelisted-ips',
                            onSuccess: () => refetch(),
                          })
                        }
                      />
                    </Space>
                  </Col>
                ))}
              </Row>
            )}
          />
          <Table.Column<User>
            title={t('actions.add')}
            render={(_, record) => (
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={() => {
                  show({
                    filterFormItems: ['ipv4'],
                    id: record.id,
                    title: t('apiWhiteList.add'),
                    formValues: { user_id: record.id, type: 2 },
                    mode: 'create',
                  });
                }}
              >
                {t('actions.add')}
              </Button>
            )}
          />
        </TableComponent>
      </List>
      <Modal />
    </>
  );
};

export default MerchantApiWhiteList;
