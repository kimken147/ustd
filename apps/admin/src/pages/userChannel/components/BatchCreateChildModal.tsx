import { FC } from 'react';
import { Modal, Form, InputNumber, message } from 'antd';
import { useCustomMutation } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { apiUrl } from 'index';

interface Props {
  parentAccountId: number;
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

/**
 * 批量建立子地址 Modal
 * 提供數量輸入，呼叫後端 API 為指定母地址批量衍生子地址
 */
export const BatchCreateChildModal: FC<Props> = ({
  parentAccountId, open, onClose, onSuccess,
}) => {
  const { t } = useTranslation('userChannel');
  const [form] = Form.useForm();
  const { mutate, isLoading } = useCustomMutation();

  const handleOk = () => {
    form.validateFields().then(values => {
      mutate({
        url: `${apiUrl}/user-channel-accounts/create-child`,
        method: 'post',
        values: {
          parent_account_id: parentAccountId,
          count: values.count,
        },
      }, {
        onSuccess: () => {
          message.success(t('messages.batchCreateSuccess'));
          form.resetFields();
          onSuccess();
          onClose();
        },
      });
    });
  };

  return (
    <Modal
      title={t('actions.batchCreateChild')}
      open={open}
      onOk={handleOk}
      onCancel={onClose}
      confirmLoading={isLoading}
      destroyOnClose
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label={t('fields.childCount')}
          name="count"
          rules={[{ required: true }]}
          initialValue={5}
        >
          <InputNumber min={1} max={50} style={{ width: '100%' }} />
        </Form.Item>
      </Form>
    </Modal>
  );
};
