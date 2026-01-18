import { SaveOutlined } from '@ant-design/icons';
import {
  Button,
  Col,
  Create,
  Form,
  Input,
  InputNumber,
  Row,
  TextField,
  useForm,
} from '@refinedev/antd';
import { useCreate } from '@refinedev/core';
import useSelector from 'hooks/useSelector';
import { Channel, Merchant, DemoCreateRes, Blue } from '@morgan-ustd/shared';
import { random } from 'lodash';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const TransitionDemoCreate: FC = () => {
  const { t } = useTranslation('transaction');

  const { Select: ChannelSelect } = useSelector<Channel>({
    valueField: 'code',
    resource: 'channels',
  });

  const { Select: MerchantSelect } = useSelector<Merchant>({
    resource: 'merchants',
    valueField: 'username',
  });

  const { form } = useForm();
  const [url, setUrl] = useState<string | null>(null);
  const [isUrlLoading] = useState(false);
  const { mutateAsync: create, isLoading: isSubmitLoading } =
    useCreate<DemoCreateRes>();

  const getRandomOrderNumber = () => {
    return `test${random(1000000000000, 1999999999999)}`;
  };

  return (
    <Create
      isLoading={isSubmitLoading || isUrlLoading}
      title={t('titles.testOrder')} // 或 t("collection.test")
      footerButtons={() => (
        <>
          <Button
            type="primary"
            icon={<SaveOutlined />}
            onClick={() => form.submit()}
          >
            {t('actions.submit')}
          </Button>
        </>
      )}
    >
      <Helmet>
        <title>{t('titles.testOrder')}</title>
      </Helmet>

      <Form
        form={form}
        layout="vertical"
        onFinish={async (values: any) => {
          const res = await create({
            values: {
              ...values,
              notify_url: `${process.env.REACT_APP_HOST}/api/v1/callback/${values.order_number}`,
            },
            successNotification: false,
            resource: 'transactions/demo',
          });

          // 提交成功後自動產生新訂單號
          form.setFieldValue('order_number', getRandomOrderNumber());
          setUrl(res.data.url);
        }}
        initialValues={{
          amount: 1000,
          order_number: getRandomOrderNumber(),
          real_name: t('placeholders.optional'), // "选填" 或可留空
          client_ip: '168.168.168.168',
        }}
      >
        <Row gutter={16}>
          <Col xs={24} md={12} lg={6}>
            <Form.Item
              label={t('fields.channel')}
              name="channel_code"
              rules={[{ required: true, message: t('messages.fieldRequired') }]} // 可選：若有通用必填訊息
            >
              <ChannelSelect />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item
              label={t('fields.merchantName')}
              name="username"
              rules={[{ required: true }]}
            >
              <MerchantSelect />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item
              label={t('fields.amount')}
              name="amount"
              rules={[{ required: true }]}
            >
              <InputNumber className="w-full" />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item
              label={t('fields.orderNumber')}
              name="order_number"
              rules={[{ required: true }]}
            >
              <Input />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item label={t('fields.realName')} name="real_name">
              <Input />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item
              label={t('fields.memberIp')}
              name="client_ip"
              rules={[{ required: true }]}
            >
              <Input />
            </Form.Item>
          </Col>

          <Col xs={24} md={12} lg={6}>
            <Form.Item label={t('fields.cashier')}>
              {url ? (
                <a href={url} target="_blank" rel="noreferrer">
                  <TextField
                    style={{ color: Blue }}
                    value={t('fields.cashierLink')}
                    copyable={{
                      text: url,
                    }}
                  />
                </a>
              ) : (
                <span>{t('placeholders.none')}</span>
              )}
            </Form.Item>
          </Col>
        </Row>
      </Form>
    </Create>
  );
};

export default TransitionDemoCreate;
