import { FC } from 'react';
import { SyncOutlined } from '@ant-design/icons';
import { Button, Modal, Space } from 'antd';
import { useCustomMutation, useDelete, useNotification } from '@refinedev/core';
import { ProviderUserChannel as UserChannel, Resource } from '@morgan-ustd/shared';
import numeral from 'numeral';

interface BatchOperationsBarProps {
  selectedKeys: React.Key[];
  setSelectedKeys: (keys: React.Key[]) => void;
  canEdit: boolean;
  apiUrl: string;
  showUpdateModal: (options: any) => void;
  refetch: () => void;
  t: (key: string, options?: Record<string, any>) => string;
  data?: UserChannel[];
}

export const BatchOperationsBar: FC<BatchOperationsBarProps> = ({
  selectedKeys,
  setSelectedKeys,
  canEdit,
  apiUrl,
  showUpdateModal,
  refetch,
  t,
  data,
}) => {
  const { mutateAsync: mutateDeleting } = useDelete();
  const { mutate: batchSyncMutate, mutation: batchSyncMutation } = useCustomMutation();
  const isSyncing = batchSyncMutation.isPending;
  const { open } = useNotification();

  if (!selectedKeys.length) {
    return null;
  }

  return (
    <div className="mb-4 block">
      <Space>
        <Button
          disabled={!canEdit}
          onClick={() => {
            showUpdateModal({
              title: t('actions.batchEditBalanceLimit'),
              customMutateConfig: {
                mutiple: selectedKeys.map(key => ({
                  url: `${apiUrl}/user-channel-accounts/${key}`,
                  id: key as string | number,
                })),
                method: 'put',
              },
              filterFormItems: ['balance_limit'],
              onSuccess: () => refetch(),
            });
          }}
        >
          {t('actions.batchEditBalanceLimit')}
        </Button>
        <Button
          disabled={!canEdit}
          onClick={() => {
            showUpdateModal({
              title: t('actions.batchEditStatus'),
              filterFormItems: ['status'],
              customMutateConfig: {
                mutiple: selectedKeys.map(key => ({
                  url: `${apiUrl}/user-channel-accounts/${key}`,
                  id: key as string | number,
                })),
                method: 'put',
              },
              onSuccess: () => refetch(),
            });
          }}
        >
          {t('actions.batchEditStatus')}
        </Button>
        <Button
          disabled={!canEdit}
          onClick={() => {
            showUpdateModal({
              title: t('actions.batchEditSingleLimit'),
              initialValues: { allow_unlimited: true },
              filterFormItems: [
                'single_min_limit',
                'single_max_limit',
                'allow_unlimited',
              ],
              customMutateConfig: {
                mutiple: selectedKeys.map(key => ({
                  url: `${apiUrl}/user-channel-accounts/${key}`,
                  id: key as string | number,
                })),
                method: 'put',
              },
              onSuccess: () => refetch(),
            });
          }}
        >
          {t('actions.batchEditSingleLimit')}
        </Button>
        <Button
          disabled={!canEdit}
          onClick={() => {
            showUpdateModal({
              title: t('actions.batchEditQuota'),
              filterFormItems: [
                'daily_limit',
                'withdraw_daily_limit',
                'monthly_limit',
                'withdraw_monthly_limit',
              ],
              customMutateConfig: {
                mutiple: selectedKeys.map(key => ({
                  url: `${apiUrl}/user-channel-accounts/${key}`,
                  id: key as string | number,
                })),
                method: 'put',
              },
              onSuccess: () => refetch(),
            });
          }}
        >
          {t('actions.batchEditQuota')}
        </Button>
        <Button
          disabled={!canEdit}
          onClick={() => {
            showUpdateModal({
              title: t('actions.batchEditCountLimit'),
              filterFormItems: [
                'daily_transaction_count_limit',
                'withdraw_daily_transaction_count_limit',
                'monthly_transaction_count_limit',
                'withdraw_monthly_transaction_count_limit',
              ],
              customMutateConfig: {
                mutiple: selectedKeys.map(key => ({
                  url: `${apiUrl}/user-channel-accounts/${key}`,
                  id: key as string | number,
                })),
                method: 'put',
              },
              onSuccess: () => refetch(),
            });
          }}
        >
          {t('actions.batchEditCountLimit')}
        </Button>
        <Button
          disabled={!canEdit || isSyncing}
          loading={isSyncing}
          icon={<SyncOutlined />}
          onClick={() =>
            Modal.confirm({
              title: t('confirmation.batchSyncBalance'),
              okText: t('actions.ok'),
              cancelText: t('actions.cancel'),
              onOk: () => {
                batchSyncMutate(
                  {
                    url: `${apiUrl}/user-channel-accounts/batch-sync`,
                    method: 'put',
                    values: { ids: selectedKeys.map(Number) },
                  },
                  {
                    onSuccess: (response) => {
                      const data = response?.data as any;
                      open?.({
                        message: t('messages.batchSyncSuccess', {
                          synced: data?.synced ?? 0,
                          total: data?.total ?? selectedKeys.length,
                        }),
                        type: 'success',
                      });
                      refetch();
                    },
                  },
                );
              },
            })
          }
        >
          {t('actions.batchSyncBalance')}
        </Button>
        <Button
          danger
          onClick={() => {
            const selectedAccounts = data?.filter(acc => selectedKeys.includes(acc.id)) ?? [];
            const accountList = selectedAccounts
              .map(acc => `${acc.account}(${numeral(acc.id).format('0000000')})`)
              .join('\n');
            Modal.confirm({
              title: t('confirmation.batchDelete'),
              content: accountList ? (
                <pre style={{ maxHeight: 200, overflow: 'auto', fontSize: 12, margin: '8px 0' }}>{accountList}</pre>
              ) : undefined,
              okText: t('actions.ok'),
              cancelText: t('actions.cancel'),
              onOk: async () => {
                const promises: Promise<any>[] = [];
                for (const key of selectedKeys) {
                  promises.push(
                    mutateDeleting({
                      resource: Resource.userChannelAccounts,
                      id: key as string | number,
                      successNotification: false,
                    })
                  );
                }
                try {
                  await Promise.all(promises);
                  open?.({
                    message: t('messages.batchDeleteSuccess'),
                    type: 'success',
                  });
                } catch (error) {
                  // Error handling is done by Refine
                }
              },
            });
          }}
        >
          {t('actions.batchDelete')}
        </Button>
        <Button onClick={() => setSelectedKeys([])}>
          {t('actions.clearAll')}
        </Button>
      </Space>
    </div>
  );
};

export default BatchOperationsBar;
