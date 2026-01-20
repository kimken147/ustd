import { Button, Space } from 'antd';
import dayjs from 'dayjs';
import type { ColumnDependencies, MerchantColumn } from './types';

export function createLastLoginColumn(deps: ColumnDependencies): MerchantColumn {
  const { t } = deps;

  return {
    title: t('fields.lastLoginTime'),
    dataIndex: 'last_login_at',
    render(value) {
      return value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : null;
    },
  };
}

export function createIpColumn(deps: ColumnDependencies): MerchantColumn {
  const { t } = deps;

  return {
    title: t('fields.lastLoginIp'),
    dataIndex: 'last_login_ipv4',
  };
}

export function createDeleteColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditProfile, Modal } = deps;

  return {
    title: t('actions.delete'),
    render(_, record) {
      return (
        <Space>
          <Button
            disabled={!canEditProfile}
            danger
            onClick={() =>
              Modal.confirm({
                id: record.id,
                values: {
                  id: record.id,
                },
                mode: 'delete',
                title: t('messages.deleteConfirm'),
              })
            }
          >
            {t('actions.delete')}
          </Button>
        </Space>
      );
    },
  };
}
