// hooks/useUserChannelForm.ts
import { useForm } from '@pankod/refine-antd';
import { useCreate } from '@pankod/refine-core';
import { useNavigate } from '@pankod/refine-react-router-v6';
import { FormValues } from '../types';
import { useTranslation } from 'react-i18next';

export const useUserChannelForm = () => {
  const { t } = useTranslation('userChannel');
  const { formProps, form } = useForm<FormValues, any, FormValues>();
  const { mutateAsync: create, isLoading: isCreateLoading } = useCreate();
  const navigate = useNavigate();

  const handleSubmit = async (values: FormValues) => {
    const formData = new FormData();
    Object.entries(values).forEach(([key, value]) => {
      if (key === 'qr_code') {
        formData.append(key, value[value.length - 1].originFileObj);
      } else if (key === 'note' && !value) {
        // Skip empty note
      } else {
        formData.append(key, value as any);
      }
    });

    formData.append('device_name', 'default');
    formData.append('type', '2'); // UserChannelType.收款
    formData.append('receiver_name', values.bank_card_holder_name || '');

    await create({
      resource: 'user-channel-accounts',
      values: formData,
      successNotification: {
        message: t('messages.createSuccess'),
        type: 'success',
      },
      metaData: {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    });
    navigate('/user-channel-accounts');
  };

  return {
    form,
    formProps,
    isCreateLoading,
    handleSubmit,
  } as const;
};
