// components/QRCodeFields.tsx
import {
  Form,
  Input,
  Upload,
  Button,
  Modal,
  Space,
  FormInstance,
} from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { FormColumn } from './FormColumn';
import { useTranslation } from 'react-i18next';

interface QRCodeFieldsProps {
  form: FormInstance<any>;
}

export const QRCodeFields: React.FC<QRCodeFieldsProps> = ({ form }) => {
  const { t } = useTranslation('userChannel');
  const qrcode = Form.useWatch('qr_code', form);
  const files = qrcode;
  const base64Url = files?.length
    ? URL.createObjectURL(files[files.length - 1].originFileObj)
    : '';

  return (
    <>
      <FormColumn>
        <Form.Item
          label={
            <Space size="small">
              <span className="text-[#ff4d4f]">*</span>
              <span>{t('placeholders.qrcode')}</span>
            </Space>
          }
        >
          <Space>
            <Form.Item
              name={['qr_code']}
              rules={[{ required: true }]}
              valuePropName="fileList"
              getValueFromEvent={e => (Array.isArray(e) ? e : e?.fileList)}
            >
              <Upload
                accept="image/*"
                showUploadList={false}
                customRequest={({ onSuccess }) => {
                  setTimeout(() => onSuccess?.('ok'), 0);
                }}
              >
                <Button icon={<UploadOutlined />}>{t('actions.upload')}</Button>
              </Upload>
            </Form.Item>
            <Form.Item>
              <Button
                onClick={() =>
                  Modal.info({
                    title: t('placeholders.viewQrcode'),
                    content: <img src={base64Url} alt="" />,
                  })
                }
                disabled={!files?.length}
              >
                {t('actions.view')}
              </Button>
            </Form.Item>
          </Space>
        </Form.Item>
      </FormColumn>
      <FormColumn>
        <Form.Item
          label={t('fields.bankCardHolderName')}
          rules={[{ required: true }]}
          name="bank_card_holder_name"
        >
          <Input />
        </Form.Item>
      </FormColumn>
    </>
  );
};
