import { Button } from 'antd';
import { RedoOutlined } from '@ant-design/icons';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createCallbackColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, apiUrl, tranStatus, customMutate } = deps;

  return {
    title: t('actions.reCallback'),
    width: 80,
    responsive: ['lg', 'xl', 'xxl'],
    render: (_, record) => {
      const { status, notify_url } = record;
      return notify_url ? (
        <Button
          icon={<RedoOutlined />}
          disabled={
            status === tranStatus.付款超时 ||
            status === tranStatus.等待付款 ||
            status === tranStatus.匹配超时
          }
          onClick={async () => {
            await customMutate({
              url: `${apiUrl}/transactions/${record.id}/renotify`,
              method: 'post',
              values: record,
              successNotification: {
                message: t('messages.callbackSuccess'),
                type: 'success',
              },
            });
          }}
        />
      ) : null;
    },
  };
}
