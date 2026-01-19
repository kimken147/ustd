import { Select as AntdSelect } from 'antd';
import type { SelectProps } from 'antd';
import { useTranslate } from '@refinedev/core';
import { SelectOption } from '../interfaces/antd';

export const TransactionCallbackStatus = {
  未通知: 0,
  通知中: 1,
  已通知: 2,
  成功: 3,
  失败: 4,
  三方处理中: 11,
} as const;

export type TransactionCallbackStatusValue =
  (typeof TransactionCallbackStatus)[keyof typeof TransactionCallbackStatus];

export function useTransactionCallbackStatus() {
  const t = useTranslate();

  const getStatusText = (status: number) => {
    switch (status) {
      case TransactionCallbackStatus.未通知:
        return t('transaction:callbackStatus.notNotified');
      case TransactionCallbackStatus.通知中:
        return t('transaction:callbackStatus.notifying');
      case TransactionCallbackStatus.已通知:
        return t('transaction:callbackStatus.notified');
      case TransactionCallbackStatus.成功:
        return t('transaction:callbackStatus.success');
      case TransactionCallbackStatus.失败:
        return t('transaction:callbackStatus.failed');
      case TransactionCallbackStatus.三方处理中:
        return t('transaction:callbackStatus.thirdPartyProcessing');
      default:
        return '';
    }
  };

  const Select = (props: SelectProps) => {
    return (
      <AntdSelect
        options={Object.values(TransactionCallbackStatus).map<SelectOption>(
          value => ({
            label: getStatusText(value),
            value,
          })
        )}
        allowClear
        {...props}
      />
    );
  };

  return {
    Select,
    getStatusText,
    Status: TransactionCallbackStatus,
  };
}

export default useTransactionCallbackStatus;
