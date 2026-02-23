// hooks/useUserChannelForm.ts
import { useForm } from '@refinedev/antd';
import { useCreate } from '@refinedev/core';
import { useNavigate } from 'react-router';
import { FormValues } from '../types';
import { useTranslation } from 'react-i18next';

export const useUserChannelForm = () => {
  const { t } = useTranslation('userChannel');
  const { formProps, form } = useForm<FormValues, any, FormValues>();
  const { mutateAsync: create, mutation } = useCreate();
  const isCreateLoading = mutation.isPending;
  const navigate = useNavigate();

  const handleSubmit = async (values: FormValues) => {
    const formData = new FormData();
    Object.entries(values).forEach(([key, value]) => {
      if (key === 'note' && !value) {
        // Skip empty note
      } else if (value != null) {
        formData.append(key, value as any);
      }
    });

    formData.append('device_name', 'default');
    formData.append('type', '2'); // UserChannelType.收款

    await create({
      resource: 'user-channel-accounts',
      values: formData,
      successNotification: {
        message: t('messages.createSuccess'),
        type: 'success',
      },
      meta: {
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
