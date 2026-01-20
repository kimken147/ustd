import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import dayjs from 'dayjs';
import type { BannedColumn, IPColumnDependencies, NameColumnDependencies } from './types';

export type { IPColumnDependencies, NameColumnDependencies } from './types';

export function useIPColumns(deps: IPColumnDependencies): BannedColumn[] {
  const { t, show, Modal } = deps;
  const resource = 'banned/ip';

  return [
    {
      title: t('banned.collectionIp'),
      dataIndex: 'ipv4',
    },
    {
      title: t('banned.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: t('banned.editNote'),
                  id: record.id,
                  filterFormItems: ['note'],
                  initialValues: { note: value },
                  resource,
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('banned.blockTime'),
      dataIndex: 'created_at',
      render: value => dayjs(value).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: t('actions.delete'),
      render(_, record) {
        return (
          <Button
            danger
            onClick={() =>
              Modal.confirm({
                title: t('banned.deleteConfirm'),
                id: record.ipv4,
                resource,
                values: { type: 1, ipv4: record.ipv4 },
                mode: 'delete',
              })
            }
          >
            {t('actions.delete')}
          </Button>
        );
      },
    },
  ];
}

export function useNameColumns(deps: NameColumnDependencies): BannedColumn[] {
  const { t, type, show, Modal } = deps;
  const resource = 'banned/realname';

  return [
    {
      title: type === 1 ? t('banned.blockedRealName') : t('banned.blockedCardHolder'),
      dataIndex: 'realname',
    },
    {
      title: t('banned.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: t('banned.editNote'),
                  id: record.id,
                  filterFormItems: ['note'],
                  initialValues: { note: value },
                  resource,
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('banned.blockTime'),
      dataIndex: 'created_at',
      render: value => dayjs(value).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: t('actions.delete'),
      render(_, record) {
        return (
          <Button
            danger
            onClick={() =>
              Modal.confirm({
                title: t('banned.deleteConfirm'),
                id: record.realname,
                resource,
                values: { type, realname: record.realname },
                mode: 'delete',
              })
            }
          >
            {t('actions.delete')}
          </Button>
        );
      },
    },
  ];
}
