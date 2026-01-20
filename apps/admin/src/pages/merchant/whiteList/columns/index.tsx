import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Col, Row, Space } from 'antd';
import type { WhitelistedIp } from '@morgan-ustd/shared';
import type { ColumnDependencies, MerchantWhiteListColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): MerchantWhiteListColumn[] {
  const { t, show, Modal, refetch } = deps;

  return [
    {
      title: t('fields.name'),
      dataIndex: 'name',
    },
    {
      title: t('fields.username'),
      dataIndex: 'username',
    },
    {
      title: t('whiteList.ipv4'),
      dataIndex: 'whitelisted_ips',
      render(value: WhitelistedIp[]) {
        return (
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
        );
      },
    },
    {
      title: t('actions.add'),
      render(_, record) {
        return (
          <Button
            type="primary"
            icon={<PlusOutlined />}
            onClick={() => {
              show({
                filterFormItems: ['ipv4', 'user_id', 'type'],
                title: t('whiteList.add'),
                formValues: { user_id: record.id, type: 1 },
                mode: 'create',
              });
            }}
          >
            {t('actions.add')}
          </Button>
        );
      },
    },
  ];
}
