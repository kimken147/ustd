import { FC, useEffect } from 'react';
import { Form, Input, InputNumber, Modal, Select } from 'antd';
import type { SelectProps } from 'antd';
import { useForm } from '@refinedev/antd';
import { useCustomMutation } from '@refinedev/core';

interface SelectOption {
  label: string;
  value: string | number;
}

interface Merchant {
  id: number;
  username: string;
  user_channels: { channel_group_id: number }[];
}

interface Provider {
  id: number;
  username: string;
}

interface Channel {
  name: string;
  channel_groups: { id: number }[];
}

interface ChannelGroup {
  id: number;
  name: string;
}

interface ThirdChannel {
  id: number;
  channel: string;
  thirdChannel: string;
}

interface UserChannelAccount {
  id: number;
  account: string;
  name: string;
}

interface CreateTransactionModalProps {
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  apiUrl: string;
  t: (key: string) => string;
  groupLabel: string;
  merchants: Merchant[] | undefined;
  providers: Provider[] | undefined;
  channels: Channel[] | undefined;
  channelGroups: ChannelGroup[] | undefined;
  thirdChannels: ThirdChannel[] | undefined;
  MerchantSelect: FC<SelectProps>;
  ProviderSelect: FC<SelectProps>;
  useUserChannelAccount: (options?: any) => {
    data: UserChannelAccount[] | undefined;
    refetch: () => void;
  };
}

export const CreateTransactionModal: FC<CreateTransactionModalProps> = ({
  open,
  onClose,
  onSuccess,
  apiUrl,
  t,
  groupLabel,
  merchants,
  providers,
  channels,
  channelGroups,
  thirdChannels,
  MerchantSelect,
  ProviderSelect,
  useUserChannelAccount,
}) => {
  const { form: createTranForm } = useForm();
  const { mutateAsync: customMutate } = useCustomMutation();

  const selectedMerchantName = Form.useWatch('merchant', createTranForm);
  const selectedProviderName = Form.useWatch('provider', createTranForm);
  const selectedChannelGroupId = Form.useWatch('channelGroup', createTranForm);
  const selectedThirdChannel = Form.useWatch('thirdchannel', createTranForm);

  const selectedMerchant = merchants?.find(
    merchant => merchant.username === selectedMerchantName
  );
  const selectedProvider = providers?.find(
    provider => provider.username === selectedProviderName
  );

  const { data: userChannelAccounts, refetch: refetchUserChannelAccounts } =
    useUserChannelAccount({
      filters: [
        {
          field: 'provider_id',
          operator: 'eq',
          value: selectedProvider?.id,
        },
        {
          field: 'channel_group',
          operator: 'eq',
          value: selectedChannelGroupId,
        },
      ],
      queryOptions: {
        enabled: false,
      },
    });

  useEffect(() => {
    if (selectedProvider && selectedChannelGroupId) {
      refetchUserChannelAccounts();
    }
  }, [refetchUserChannelAccounts, selectedProvider, selectedChannelGroupId]);

  const handleOk = () => {
    Modal.confirm({
      title: t('messages.confirmCreateEmptyOrderFinal'),
      onOk: async () => {
        await createTranForm.validateFields();
        await customMutate({
          url: `${apiUrl}/transactions`,
          method: 'post',
          values: {
            ...createTranForm.getFieldsValue(),
            merchant: selectedMerchant?.id,
            provider: selectedProvider?.id,
          },
        });
        createTranForm.resetFields();
        onSuccess();
        onClose();
      },
    });
  };

  const channelGroupOptions: SelectProps['options'] = merchants
    ?.find(m => m.username === selectedMerchantName)
    ?.user_channels.map<SelectOption>(userChannel => ({
      value: userChannel.channel_group_id,
      label: channelGroups?.find(
        channelGroup => channelGroup.id === userChannel.channel_group_id
      )?.name ?? '',
    }));

  const channel = channels?.find(c =>
    c.channel_groups.find(cg => cg.id === selectedChannelGroupId)
  );

  const thirdChannelOptions: SelectProps['options'] = thirdChannels
    ?.filter(tc => tc.channel === channel?.name)
    .map(tc => ({
      label: `${tc.thirdChannel}-${tc.channel}`,
      value: tc.id,
    }));

  return (
    <Modal
      open={open}
      title={t('actions.createEmptyOrder')}
      onCancel={onClose}
      onOk={handleOk}
    >
      <Form form={createTranForm} layout="vertical">
        <Form.Item
          label={t('placeholders.selectMerchant')}
          name="merchant"
          rules={[{ required: true }]}
        >
          <MerchantSelect />
        </Form.Item>

        <Form.Item
          label={t('placeholders.selectChannel')}
          name="channelGroup"
          rules={[{ required: true }]}
        >
          <Select
            showSearch
            allowClear
            optionFilterProp="label"
            options={channelGroupOptions}
          />
        </Form.Item>

        <Form.Item
          name="provider"
          label={groupLabel}
          validateStatus={selectedThirdChannel ? 'success' : undefined}
          help={selectedThirdChannel ? '' : undefined}
          rules={[{ required: !selectedThirdChannel }]}
        >
          <ProviderSelect
            disabled={selectedThirdChannel}
            onChange={value => {
              if (value) {
                createTranForm.setFieldValue('thirdchannel', undefined);
              }
            }}
          />
        </Form.Item>

        <Form.Item
          name="account"
          label={t('fields.collectionNumber')}
          validateStatus={selectedThirdChannel ? 'success' : undefined}
          help={selectedThirdChannel ? '' : undefined}
          rules={[{ required: !selectedThirdChannel }]}
        >
          <Select
            disabled={selectedThirdChannel}
            options={
              selectedProvider && selectedChannelGroupId
                ? userChannelAccounts?.map<SelectOption>(userChannelAccount => ({
                    label: `${userChannelAccount.account}(${userChannelAccount.name})`,
                    value: userChannelAccount.id,
                  }))
                : []
            }
            allowClear
          />
        </Form.Item>

        <Form.Item
          name="thirdchannel"
          label={t('placeholders.selectThirdParty')}
          rules={[{ required: !selectedProviderName }]}
        >
          <Select
            options={thirdChannelOptions}
            disabled={selectedProviderName}
            allowClear
            onChange={value => {
              if (value) {
                createTranForm.setFieldsValue({
                  account: undefined,
                  provider: undefined,
                });
              }
            }}
          />
        </Form.Item>

        <Form.Item
          label={t('fields.amount')}
          name="amount"
          rules={[{ required: true }]}
        >
          <InputNumber className="w-full" />
        </Form.Item>

        <Form.Item
          label={t('fields.note')}
          name="note"
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
      </Form>
    </Modal>
  );
};

export default CreateTransactionModal;
