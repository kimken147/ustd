import { FC } from 'react';
import { Button, Space } from 'antd';
import { UpdateMerchantParams } from './columns';

interface BatchOperationsBarProps {
  selectedKeys: React.Key[];
  canEditProfile: boolean;
  apiUrl: string;
  t: (key: string) => string;
  show: (options: any) => void;
  refetch: () => void;
  onClearSelection: () => void;
}

const BatchOperationsBar: FC<BatchOperationsBarProps> = ({
  selectedKeys,
  canEditProfile,
  apiUrl,
  t,
  show,
  refetch,
  onClearSelection,
}) => {
  if (!selectedKeys.length) {
    return null;
  }

  const handleBatchUpdate = (title: string, filterFormItems: string[]) => {
    show({
      title,
      filterFormItems,
      customMutateConfig: {
        mutiple: selectedKeys.map(key => ({
          url: `${apiUrl}/merchants/${key}`,
          id: key as string | number,
        })),
        method: 'put',
      },
      onSuccess: () => refetch(),
    });
  };

  return (
    <div className="mb-4 block">
      <Space>
        <Button
          disabled={!canEditProfile}
          onClick={() =>
            handleBatchUpdate(t('switches.transactionEnable'), [
              UpdateMerchantParams.transaction_enable,
            ])
          }
        >
          {t('batchActions.batchUpdateTransaction')}
        </Button>
        <Button
          disabled={!canEditProfile}
          onClick={() =>
            handleBatchUpdate(t('switches.agencyWithdrawEnable'), [
              UpdateMerchantParams.agency_withdraw_enable,
            ])
          }
        >
          {t('batchActions.batchUpdateAgencyWithdraw')}
        </Button>
        <Button
          disabled={!canEditProfile}
          onClick={() =>
            handleBatchUpdate(t('switches.withdrawEnable'), [
              UpdateMerchantParams.withdraw_enable,
            ])
          }
        >
          {t('batchActions.batchUpdateWithdraw')}
        </Button>
        <Button onClick={onClearSelection}>{t('batchActions.clearSelection')}</Button>
      </Space>
    </div>
  );
};

export default BatchOperationsBar;
