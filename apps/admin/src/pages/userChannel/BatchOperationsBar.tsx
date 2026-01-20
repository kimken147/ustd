import { FC } from 'react';
import { Button, Modal, Space } from 'antd';
import { useDelete, useNotification } from '@refinedev/core';
import { Resource } from '@morgan-ustd/shared';

interface BatchOperationsBarProps {
  selectedKeys: React.Key[];
  setSelectedKeys: (keys: React.Key[]) => void;
  canEdit: boolean;
  apiUrl: string;
  showUpdateModal: (options: any) => void;
  refetch: () => void;
  t: (key: string) => string;
}

export const BatchOperationsBar: FC<BatchOperationsBarProps> = ({
  selectedKeys,
  setSelectedKeys,
  canEdit,
  apiUrl,
  showUpdateModal,
  refetch,
  t,
}) => {
  const { mutateAsync: mutateDeleting } = useDelete();
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
          danger
          onClick={() =>
            Modal.confirm({
              title: t('confirmation.batchDelete'),
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
            })
          }
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
