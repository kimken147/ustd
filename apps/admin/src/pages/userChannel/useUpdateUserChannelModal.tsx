import { Form, Modal, useForm, useModal } from '@pankod/refine-antd';
import { HttpError } from '@pankod/refine-core';
import useUserChannelMuation from 'hooks/useChannelMutation';
import { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import { FC, PropsWithChildren, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  userChannel?: UserChannel;
  onSuccess: (data: UserChannel) => void;
  title: string;
};

const useUpdateUserChannelModal = ({
  userChannel,
  onSuccess,
  title,
}: Props) => {
  const { t } = useTranslation('userChannel');
  const {
    mutate: mutateChannel,
    isLoading,
    isSuccess,
    isError,
  } = useUserChannelMuation();
  const { formProps, form } = useForm<
    UserChannel,
    HttpError,
    IUpdateUserChannel
  >();
  const { modalProps, show, close } = useModal({
    modalProps: {
      destroyOnClose: true,
      okText: t('actions.submit'),
      cancelText: t('actions.cancel'),
      title: t('titles.edit'),
      onOk: async () => {
        Modal.confirm({
          title: t('confirmation.modify'),
          onOk: async () => {
            try {
              await form?.validateFields();
              mutateChannel({
                query: { ...form?.getFieldsValue(), id: userChannel?.id },
                onSuccess: data => {
                  onSuccess(data);
                  close();
                },
              });
            } catch (error) {
              console.log(error);
            }
          },
          okText: t('actions.submit'),
          cancelText: t('actions.cancel'),
        });
      },
      okButtonProps: {
        loading: isLoading,
      },
    },
  });

  useEffect(() => {
    form?.setFieldsValue({
      id: userChannel?.id,
      provider_id: userChannel?.user.id,
      balance: Number(userChannel?.balance),
      daily_limit: Number(userChannel?.daily_limit),
      withdraw_daily_limit: Number(userChannel?.withdraw_daily_limit),
      monthly_limit: Number(userChannel?.monthly_limit_value),
      withdraw_monthly_limit: Number(userChannel?.withdraw_monthly_limit),
    });
  }, [form, userChannel]);

  const modal: FC<PropsWithChildren> = ({ children }) => (
    <Modal {...modalProps} destroyOnClose title={title}>
      <Form {...formProps} form={form} layout="vertical">
        {children}
      </Form>
    </Modal>
  );

  return {
    modal,
    show,
    isLoading,
    isSuccess,
    isError,
  };
};

export default useUpdateUserChannelModal;
