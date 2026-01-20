import { Button, Modal, Space } from 'antd';
import { Resource } from '@morgan-ustd/shared';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createOperationColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, monthEnable, canDelete, mutateDeleting } = deps;

  if (!monthEnable) return null;

  return {
    title: t('fields.operation'),
    render(_, record) {
      return (
        <Space>
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
        </Space>
      );
    },
  };
}
