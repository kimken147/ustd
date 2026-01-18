// components/BankFields.tsx
import {
  Form,
  Input,
} from 'antd';
import { FormColumn } from './FormColumn';
import { useTranslation } from 'react-i18next';

export const BankFields: React.FC<{ BankSelect: any }> = ({ BankSelect }) => {
  const { t } = useTranslation('userChannel');
  return (
    <>
      <FormColumn>
        <Form.Item
          label={t('fields.bankName')}
          name="bank_name"
          rules={[{ required: true }]}
        >
          <BankSelect />
        </Form.Item>
      </FormColumn>
      <FormColumn>
        <Form.Item
          label={t('fields.bankBranch')}
          name="bank_card_branch"
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
      </FormColumn>
      <FormColumn>
        <Form.Item
          label={t('fields.bankCardHolder')}
          name="bank_card_holder_name"
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
      </FormColumn>
    </>
  );
};
