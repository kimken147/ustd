// useWithdrawStatus.tsx
import { Select as AntdSelect, SelectProps } from '@pankod/refine-antd';
import { useTranslation } from 'react-i18next';

type Options = NonNullable<SelectProps['options']>;
type Option = Options[0];

function useWithdrawStatus() {
  const { t } = useTranslation();

  const Status = {
    审核中: 1,
    匹配中: 2,
    等待付款: 3,
    成功: 4,
    手动成功: 5,
    匹配超时: 6,
    支付超时: 7,
    失败: 8,
    三方处理中: 11,
  };

  const getStatusText = (status: number) => {
    switch (status) {
      case Status.审核中:
        return t('transaction:withdrawStatus.reviewing');
      case Status.匹配中:
        return t('transaction:withdrawStatus.matching');
      case Status.等待付款:
        return t('transaction:withdrawStatus.waitingPayment');
      case Status.成功:
        return t('transaction:withdrawStatus.success');
      case Status.手动成功:
        return t('transaction:withdrawStatus.manualSuccess');
      case Status.匹配超时:
        return t('transaction:withdrawStatus.matchTimeout');
      case Status.支付超时:
        return t('transaction:withdrawStatus.paymentTimeout');
      case Status.失败:
        return t('transaction:withdrawStatus.failed');
      case Status.三方处理中:
        return t('transaction:withdrawStatus.thirdPartyProcessing');
      default:
        return '';
    }
  };

  const Select = (props: SelectProps) => {
    return (
      <AntdSelect
        options={Object.values(Status).map<Option>(value => ({
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
    Status,
  };
}

export default useWithdrawStatus;
