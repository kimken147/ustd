import { Select as AntdSelect, SelectProps } from '@pankod/refine-antd';
import { useTranslation } from 'react-i18next';

type Options = NonNullable<SelectProps['options']>;
type Option = Options[0];

function useTransactionStatus() {
  const { t } = useTranslation('transaction');

  const Status = {
    已建立: 1,
    匹配中: 2,
    等待付款: 3,
    成功: 4,
    手动成功: 5,
    匹配超时: 6,
    付款超时: 7,
    失败: 8,
    三方处理中: 11,
  };

  const getStatusText = (status: number) => {
    switch (status) {
      case Status.已建立:
        return t('transactionStatus.created');
      case Status.匹配中:
        return t('transactionStatus.matching');
      case Status.等待付款:
        return t('transactionStatus.waitingPayment');
      case Status.成功:
        return t('transactionStatus.success');
      case Status.手动成功:
        return t('transactionStatus.manualSuccess');
      case Status.匹配超时:
        return t('transactionStatus.matchTimeout');
      case Status.付款超时:
        return t('transactionStatus.paymentTimeout');
      case Status.失败:
        return t('transactionStatus.failed');
      case Status.三方处理中:
        return t('transactionStatus.thirdPartyProcessing');
      default:
        return '';
    }
  };

  const selectProps: SelectProps = {
    options: Object.values(Status).map<Option>(value => ({
      label: getStatusText(value),
      value,
    })),
    allowClear: true,
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
    selectProps,
  };
}

export default useTransactionStatus;
