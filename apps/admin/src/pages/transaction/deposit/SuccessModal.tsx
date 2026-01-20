import { FC } from 'react';
import { Checkbox, Form, Modal, Select } from 'antd';
import { useForm } from '@refinedev/antd';
import { useUpdate } from '@refinedev/core';
import numeral from 'numeral';
import type { Deposit } from 'interfaces/deposit';

interface SuccessModalProps {
  open: boolean;
  current: Deposit | undefined;
  onClose: () => void;
  t: (key: string, options?: Record<string, any>) => string;
}

export const SuccessModal: FC<SuccessModalProps> = ({
  open,
  current,
  onClose,
  t,
}) => {
  const { form } = useForm();
  const { mutateAsync: update } = useUpdate();

  const handleFinish = async (values: any) => {
    if (!current) return;

    await update({
      resource: 'deposits',
      id: current.id,
      values: {
        ...values,
        id: current.id,
        status: 5,
      },
    });
    onClose();
  };

  const frozenBalance = numeral(current?.provider?.wallet?.frozen_balance).value() ?? 0;
  const amount = numeral(current?.amount).value() ?? 0;
  const showDeductOption = frozenBalance > amount;

  return (
    <Modal
      open={open}
      onCancel={onClose}
      onOk={form.submit}
      title={t('actions.changeToSuccess')}
    >
      <Form
        layout="vertical"
        form={form}
        onFinish={handleFinish}
        initialValues={{
          delay_settle_minutes: 0,
          deduct_frozen_balance: false,
        }}
      >
        <Form.Item
          label={t('fields.delaySettleMinutes')}
          name="delay_settle_minutes"
          rules={[{ required: true }]}
        >
          <Select
            options={[
              { label: t('buttons.instant'), value: 0 },
              { label: t('buttons.5min'), value: 5 },
              { label: t('buttons.10min'), value: 10 },
              { label: t('buttons.15min'), value: 15 },
            ]}
          />
        </Form.Item>
        {showDeductOption && (
          <Form.Item
            label={t('fields.deductFrozenBalance')}
            name="deduct_frozen_balance"
            valuePropName="checked"
          >
            <Checkbox>
              {t('messages.deductFrozenBalanceTip', {
                amount: current?.provider?.wallet?.frozen_balance ?? 0,
              })}
            </Checkbox>
          </Form.Item>
        )}
      </Form>
    </Modal>
  );
};

export default SuccessModal;
