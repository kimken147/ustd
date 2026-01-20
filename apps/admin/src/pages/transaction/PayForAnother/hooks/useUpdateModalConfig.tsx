import { Input, Select, SelectProps } from 'antd';
import type { FormItemProps } from 'antd';
import { useTranslation } from 'react-i18next';
import { useUpdateModal, UseUpdateModalProps } from '@morgan-ustd/shared';

interface UseUpdateModalConfigProps {
  providerSelectProps: SelectProps;
  currentMerchantThirdChannelSelect: SelectProps['options'];
}

/**
 * Hook that returns configured useUpdateModal with PayForAnother form items
 */
export function useUpdateModalConfig({
  providerSelectProps,
  currentMerchantThirdChannelSelect,
}: UseUpdateModalConfigProps) {
  const { t } = useTranslation('transaction');

  const formItems: FormItemProps[] = [
    {
      label: t('fields.note'),
      name: 'note',
      children: <Input.TextArea />,
    },
    { name: 'realname', hidden: true },
    { name: 'type', hidden: true },
    { name: 'ipv4', hidden: true },
    { name: 'transaction_id', hidden: true },
    {
      name: 'to_thirdchannel_id',
      children: <Select options={currentMerchantThirdChannelSelect} />,
    },
    {
      name: 'withdrawType',
      label: t('withdraw.type'),
      children: (
        <Select
          options={[
            { label: t('types.manualAgency'), value: 4 },
            { label: t('types.paufenAgency'), value: 2 },
          ]}
        />
      ),
    },
    {
      name: 'to_id',
      label: t('fields.assignProvider'),
      children: (
        <Select
          {...providerSelectProps}
          options={[
            { label: t('placeholders.notAssign'), value: null },
            ...(providerSelectProps.options ?? []),
          ]}
        />
      ),
    },
  ];

  const transferFormValues = (record: Record<string, any>) => {
    if (record.withdrawType) {
      return { ...record, type: record.withdrawType };
    }
    return record;
  };

  return useUpdateModal({
    formItems,
    transferFormValues,
  });
}

export default useUpdateModalConfig;
