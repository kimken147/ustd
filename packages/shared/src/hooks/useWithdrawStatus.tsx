import { Select as AntdSelect } from 'antd';
import type { SelectProps } from 'antd';
import { useTranslate } from '@refinedev/core';

type Options = NonNullable<SelectProps['options']>;
type Option = Options[0];

export const WithdrawStatus = {
  审核中: 1,
  匹配中: 2,
  等待付款: 3,
  成功: 4,
  手动成功: 5,
  匹配超时: 6,
  支付超时: 7,
  失败: 8,
  三方处理中: 11,
} as const;

export type WithdrawStatusValue =
  (typeof WithdrawStatus)[keyof typeof WithdrawStatus];

export function useWithdrawStatus() {
  const t = useTranslate();

  const getStatusText = (status: number) => {
    switch (status) {
      case WithdrawStatus.审核中:
        return t('transaction:withdrawStatus.reviewing');
      case WithdrawStatus.匹配中:
        return t('transaction:withdrawStatus.matching');
      case WithdrawStatus.等待付款:
        return t('transaction:withdrawStatus.waitingPayment');
      case WithdrawStatus.成功:
        return t('transaction:withdrawStatus.success');
      case WithdrawStatus.手动成功:
        return t('transaction:withdrawStatus.manualSuccess');
      case WithdrawStatus.匹配超时:
        return t('transaction:withdrawStatus.matchTimeout');
      case WithdrawStatus.支付超时:
        return t('transaction:withdrawStatus.paymentTimeout');
      case WithdrawStatus.失败:
        return t('transaction:withdrawStatus.failed');
      case WithdrawStatus.三方处理中:
        return t('transaction:withdrawStatus.thirdPartyProcessing');
      default:
        return '';
    }
  };

  const Select = (props: SelectProps) => {
    return (
      <AntdSelect
        options={Object.values(WithdrawStatus).map<Option>(value => ({
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
    Status: WithdrawStatus,
  };
}

export default useWithdrawStatus;
