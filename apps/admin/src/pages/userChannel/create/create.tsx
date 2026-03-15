// UserChannelCreate.tsx
import { IResourceComponentsProps } from '@refinedev/core';
import {
  Create,
  TextField,
  SaveButton,
} from '@refinedev/antd';
import {
  Form,
  Row,
  Select,
  Input,
  InputNumber,
  Col,
} from 'antd';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import useSelector from 'hooks/useSelector';
import { useTerminology } from 'contexts/AppModeContext';
import { useUserChannelForm } from './hooks/useUserChannelForm';
import { FormColumn } from './components/FormColumn';

export const UserChannelCreate: React.FC<IResourceComponentsProps> = () => {
  const { t } = useTranslation('userChannel');
  const { providerName: providerLabel } = useTerminology();
  const title = t('titles.create');

  const { form, isCreateLoading, handleSubmit } = useUserChannelForm();

  const { Select: ChannelAmountSelect, data: channelAmounts } = useSelector({
    resource: 'channel-amounts',
  });

  const { selectProps: ProviderSelectProps } = useSelector({
    resource: 'providers',
  });

  const curChannelAmountId = Form.useWatch('channel_amount_id', form);
  const curChannelCode = channelAmounts?.find(
    item => item.id === curChannelAmountId
  )?.channel_code as string;
  // 監聽地址類型切換，用於條件顯示母地址選擇器
  const curAddressType = Form.useWatch('address_type', form);
  const curChainNetwork = Form.useWatch('chain_network', form);

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <Create
        title={title}
        footerButtons={
          <SaveButton onClick={form.submit} loading={isCreateLoading}>
            {t('actions.submit')}
          </SaveButton>
        }
      >
        <Form
          form={form}
          layout="vertical"
          initialValues={{
            is_auto: 0,
            balance: 0,
            balance_limit: 0,
            note: '',
          }}
          onFinish={handleSubmit}
        >
          <Row gutter={16}>
            <FormColumn>
              <Form.Item
                label={providerLabel}
                name="provider"
                rules={[{ required: true }]}
              >
                <Select {...ProviderSelectProps} />
              </Form.Item>
            </FormColumn>
            <FormColumn>
              <Form.Item
                label={t('fields.channel')}
                name="channel_amount_id"
                rules={[{ required: true }]}
              >
                <ChannelAmountSelect />
              </Form.Item>
            </FormColumn>

            {curChannelCode && (
              <FormColumn>
                <Form.Item
                  label={t('fields.account')}
                  name="bank_card_number"
                  rules={[
                    { required: true },
                    ...(curChannelCode === 'USDT'
                      ? (curChainNetwork === 'erc20' || curChainNetwork === 'bep20')
                        ? [{ pattern: /^0x[0-9a-fA-F]{40}$/, message: t('validation.invalidEvmAddress') }]
                        : [{ pattern: /^T[1-9A-HJ-NP-Za-km-z]{33}$/, message: t('validation.invalidTronAddress') }]
                      : []),
                  ]}
                >
                  <Input placeholder={curChannelCode === 'USDT' ? t('placeholders.walletAddress') : ''} />
                </Form.Item>
              </FormColumn>
            )}

            {curChannelCode === 'USDT' && (
              <FormColumn>
                <Form.Item
                  label={t('fields.chainNetwork')}
                  name="chain_network"
                  rules={[{ required: true }]}
                  initialValue="trc20"
                >
                  <Select
                    options={[
                      { label: 'TRC-20', value: 'trc20' },
                      { label: 'ERC-20', value: 'erc20' },
                      { label: 'BEP-20', value: 'bep20' },
                    ]}
                  />
                </Form.Item>
              </FormColumn>
            )}

            {/* USDT 地址類型選擇：主地址或子地址 */}
            {curChannelCode === 'USDT' && (
              <FormColumn>
                <Form.Item
                  label={t('fields.addressType')}
                  name="address_type"
                  initialValue="master"
                >
                  <Select
                    options={[
                      { label: t('fields.masterAddress'), value: 'master' },
                      { label: t('fields.childAddress'), value: 'child' },
                    ]}
                  />
                </Form.Item>
              </FormColumn>
            )}

            {/* 子地址時顯示母地址選擇器 */}
            {curChannelCode === 'USDT' && curAddressType === 'child' && (
              <FormColumn>
                <Form.Item
                  label={t('fields.parentAccount')}
                  name="parent_account_id"
                >
                  <Select
                    allowClear
                    showSearch
                    filterOption={(input, option) =>
                      String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
                    }
                    placeholder={t('placeholders.selectParentAccount')}
                    options={[] as { label: string; value: number }[]}
                  />
                </Form.Item>
              </FormColumn>
            )}

            {curChannelCode === 'USDT' && (
              <FormColumn>
                <Form.Item
                  label={t('fields.privateKey')}
                  name="private_key"
                >
                  <Input.Password placeholder={t('placeholders.privateKey')} />
                </Form.Item>
              </FormColumn>
            )}

            <FormColumn>
              <Row gutter={10}>
                <Col span={11}>
                  <Form.Item
                    label={t('fields.singleLimit')}
                    name="single_min_limit"
                  >
                    <InputNumber className="w-full" />
                  </Form.Item>
                </Col>
                <Col span={2} className="flex justify-center items-center">
                  <TextField value="~" />
                </Col>
                <Col span={11}>
                  <Form.Item label=" " name="single_max_limit">
                    <InputNumber className="w-full" />
                  </Form.Item>
                </Col>
              </Row>
            </FormColumn>

            <FormColumn>
              <Form.Item label={t('fields.note')} name="note">
                <Input />
              </Form.Item>
            </FormColumn>
          </Row>
        </Form>
      </Create>
    </>
  );
};

export default UserChannelCreate;
