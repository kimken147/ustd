import { Button, Modal, Space } from 'antd';
import { Resource } from '@morgan-ustd/shared';
import { Link } from 'react-router';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createOperationColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, monthEnable, canDelete, mutateDeleting } = deps;

  return {
    title: t('fields.operation'),
    render(_, record) {
      return (
        <Space>
          <Link to={`/user-channel-accounts/${record.id}`}>
            <Button type="link" size="small">
              {t('actions.show')}
            </Button>
          </Link>
          {monthEnable && (
            <Button
              disabled={!canDelete}
              danger
              className={canDelete ? '!text-[#ff4d4f]' : ''}
              onClick={() => {
                Modal.confirm({
                  title: t('confirmation.deleteAccount'),
                  okText: t('actions.ok'),
                  cancelText: t('actions.cancel'),
                  onOk: () => {
                    mutateDeleting({
                      resource: Resource.userChannelAccounts,
                      id: record.id,
                    });
                  },
                });
              }}
            >
              {t('actions.delete')}
            </Button>
          )}
        </Space>
      );
    },
  };
}
