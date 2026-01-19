import {
  Create,
  SaveButton,
  useForm,
} from '@refinedev/antd';
import {
  Form,
  Input,
  InputNumber,
  Radio,
} from 'antd';
import { useCreate } from '@refinedev/core';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

const DepositRewardCreate: FC = () => {
  const { t } = useTranslation('transaction');
  const title = t('titles.quickChargeRewardCreate');
  const { form } = useForm();
  const { mutateAsync: create } = useCreate();
  const navigate = useNavigate();
  const goBack = () => navigate(-1);
  return (
    <>
      <Helmet>{title}</Helmet>
      <Create
        title={title}
        footerButtons={
          <>
            <SaveButton onClick={form.submit}>{t('actions.submit')}</SaveButton>
          </>
        }
      >
        <Form
          layout="vertical"
          form={form}
          onFinish={async values => {
            await create({
              values,
              resource: 'matching-deposit-rewards',
              successNotification: {
                message: t('messages.createSuccess'),
                type: 'success',
              },
            });
            goBack();
          }}
          initialValues={{
            reward_unit: 1,
          }}
        >
          <Form.Item
            label={t('fields.amountRange')}
            name={'amount'}
            rules={[{ required: true }]}
          >
            <Input />
          </Form.Item>
          <Form.Item label={t('fields.rewardMode')} name={'reward_unit'}>
            <Radio.Group>
              <Radio value={1}>{t('types.perOrderReward')}</Radio>
              <Radio value={2}>{t('types.percentageReward')}</Radio>
            </Radio.Group>
          </Form.Item>
          <Form.Item
            label={t('fields.rewardCommission')}
            name={'reward_amount'}
            rules={[{ required: true }]}
          >
            <InputNumber className="w-full" />
          </Form.Item>
        </Form>
      </Create>
    </>
  );
};

export default DepositRewardCreate;
