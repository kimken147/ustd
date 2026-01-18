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

const MerchantWhiteList: FC = () => {
  const { t } = useTranslation('merchant');

  const {
    Form,
    Table: TableComponent,
    refetch,
  } = useTable<User>({
    resource: 'users',
    filters: [
      {
        field: 'include[]',
        value: 'whitelisted_ips',
        operator: 'eq',
      },
      {
        field: 'role',
        value: 3,
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
        label: t('fields.merchantOrAccount'),
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
        rules: [{ required: true, message: t('validation.required') }], // 可選：加入驗證訊息
      },
    ],
    onSuccess: () => {
      refetch();
    },
  });

  return (
    <>
      <Helmet>
        <title>{t('titles.loginWhiteList')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader
            title={t('titles.loginWhiteList')}
            resource="merchants"
          />
        }
      >
        <Form />
        <Divider />
        <TableComponent>
          <Table.Column title={t('fields.name')} dataIndex={'name'} />
          <Table.Column title={t('fields.username')} dataIndex={'username'} />
          <Table.Column
            title={t('whiteList.ipv4')}
            dataIndex={'whitelisted_ips'}
            render={(value: Array<WhitelistedIp>) => (
              <Row gutter={[16, 16]}>
                {value.map(list => (
                  <Col key={list.id}>
                    <Space>
                      <TextField value={list.ipv4} />
                      <EditOutlined
                        style={{ color: '#6eb9ff' }}
                        onClick={() => {
                          show({
                            title: t('whiteList.edit'),
                            id: list.id,
                            resource: 'whitelisted-ips',
                            filterFormItems: ['ipv4'],
                            initialValues: { ipv4: list.ipv4 },
                          });
                        }}
                      />
                      <DeleteOutlined
                        style={{ color: '#ff4d4f' }}
                        onClick={() => {
                          Modal.confirm({
                            title: t('whiteList.confirmDelete'),
                            id: list.id,
                            mode: 'delete',
                            values: {},
                            resource: 'whitelisted-ips',
                            onSuccess: () => refetch(),
                          });
                        }}
                      />
                    </Space>
                  </Col>
                ))}
              </Row>
            )}
          />
          <Table.Column<User>
            title={t('actions.add')}
            render={(_, record) => {
              return (
                <Button
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={() => {
                    show({
                      filterFormItems: ['ipv4', 'user_id', 'type'],
                      title: t('whiteList.add'),
                      formValues: {
                        user_id: record.id,
                        type: 1,
                      },
                      mode: 'create',
                    });
                  }}
                >
                  {t('actions.add')}
                </Button>
              );
            }}
          />
        </TableComponent>
        <Modal
          defaultValue={{
            type: 1,
          }}
        />
      </List>
    </>
  );
};

export default MerchantWhiteList;
