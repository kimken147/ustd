import { Select as AntdSelect } from 'antd';
import type { SelectProps } from 'antd';
import { useTranslate } from '@refinedev/core';

type Options = NonNullable<SelectProps['options']>;
type Option = Options[0];

export const TransactionStatus = {
  已建立: 1,
  匹配中: 2,
  等待付款: 3,
  成功: 4,
  手动成功: 5,
  匹配超时: 6,
  付款超时: 7,
  失败: 8,
  三方处理中: 11,
} as const;

export type TransactionStatusValue =
  (typeof TransactionStatus)[keyof typeof TransactionStatus];

export function useTransactionStatus() {
  const t = useTranslate();

  const getStatusText = (status: number) => {
    switch (status) {
      case TransactionStatus.已建立:
        return t('transaction:transactionStatus.created');
      case TransactionStatus.匹配中:
        return t('transaction:transactionStatus.matching');
      case TransactionStatus.等待付款:
        return t('transaction:transactionStatus.waitingPayment');
      case TransactionStatus.成功:
        return t('transaction:transactionStatus.success');
      case TransactionStatus.手动成功:
        return t('transaction:transactionStatus.manualSuccess');
      case TransactionStatus.匹配超时:
        return t('transaction:transactionStatus.matchTimeout');
      case TransactionStatus.付款超时:
        return t('transaction:transactionStatus.paymentTimeout');
      case TransactionStatus.失败:
        return t('transaction:transactionStatus.failed');
      case TransactionStatus.三方处理中:
        return t('transaction:transactionStatus.thirdPartyProcessing');
      default:
        return '';
    }
  };

  const Select = (props: SelectProps) => {
    return (
      <AntdSelect
        options={Object.values(TransactionStatus).map<Option>(value => ({
          label: getStatusText(value),
          value,
        }))}
        allowClear
        {...props}
      />
    );
  };

  return {
    Select,
    getStatusText,
    Status: TransactionStatus,
  };
}

export default useTransactionStatus;
