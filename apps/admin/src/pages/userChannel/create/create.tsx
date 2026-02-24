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
import { useUserChannelForm } from './hooks/useUserChannelForm';
import { FormColumn } from './components/FormColumn';

export const UserChannelCreate: React.FC<IResourceComponentsProps> = () => {
  const { t } = useTranslation('userChannel');
  const isPaufen = process.env.REACT_APP_IS_PAUFEN;
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
                label={
                  isPaufen ? t('fields.providerName') : t('fields.groupName')
                }
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
                    ...(curChannelCode === 'USDT' ? [{
                      pattern: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
                      message: t('validation.invalidTronAddress'),
                    }] : []),
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
