import { gold, green, red } from '@ant-design/colors';
import { UserOutlined } from '@ant-design/icons';
import {
  Avatar,
  Button,
  Col,
  ColProps,
  Form,
  Input,
  Modal,
  Row,
  useForm,
  useModal,
} from '@pankod/refine-antd';
import {
  useApiUrl,
  useCustomMutation,
  useGetIdentity,
} from '@pankod/refine-core';
import { FC } from 'react';
import { useTranslation } from 'react-i18next';

const HomePage: FC = () => {
  const colProps: ColProps = {
    span: 12,
    style: {
      display: 'flex',
      justifyContent: 'center',
      alignItems: 'center',
      padding: 16,
    },
  };
  const { data: user, isLoading } = useGetIdentity<Profile>();
  const apiUrl = useApiUrl();
  const { mutateAsync, isLoading: isUpdatePassowrdLoading } =
    useCustomMutation<IChangePasswordReq>();
  const { t } = useTranslation();

  const { form, formProps } = useForm();
  const { modalProps, show, close } = useModal({
    modalProps: {
      title: t('home.values.changePassword'),
      onOk: async () => {
        try {
          await form.validateFields();
          await mutateAsync({
            url: `${apiUrl}/change-password`,
            method: 'post',
            values: form.getFieldsValue([
              'old_password',
              'new_password',
              'one_time_password',
            ]),
            successNotification: {
              message: t('success'),
              type: 'success',
            },
          });
          close();
        } catch (error) {
          console.error(error);
        }
      },
      okButtonProps: {
        loading: isUpdatePassowrdLoading,
      },
    },
  });
  if (isLoading) return null;
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        maxWidth: '480px',
        margin: '0 auto',
      }}
    >
      <Avatar size={100} icon={<UserOutlined />} />
      <Row gutter={16} style={{ marginTop: 20, width: '100%' }}>
        <Col {...colProps}>{t('home.fields.username')}</Col>
        <Col {...colProps}>
          <span style={{ color: gold[8] }}>{user?.name}</span>
        </Col>
        <Col {...colProps}>{t('home.fields.password')}</Col>
        <Col {...colProps}>
          <Button onClick={() => show()}>
            {t('home.values.changePassword')}
          </Button>
        </Col>
        <Col {...colProps}>{t('home.fields.accountStatus')}</Col>
        <Col {...colProps}>
          {user?.status ? (
            <div>
              <span
                style={{
                  width: 8,
                  height: 8,
                  borderRadius: '50%',
                  marginRight: 5,
                  background: green[6],
                  display: 'inline-block',
                }}
              ></span>
              <span style={{ color: green[6] }}>{t('status.enable')}</span>
            </div>
          ) : (
            <div>
              <span
                style={{
                  width: 8,
                  height: 8,
                  borderRadius: '50%',
                  marginRight: 5,
                  background: red[6],
                  display: 'inline-block',
                }}
              ></span>
              <span style={{ color: red[6] }}>{t('status.disable')}</span>
            </div>
          )}
        </Col>
        {/* <Col {...colProps}>谷歌验证码启用状态</Col>
                <Col {...colProps}>
                    <Switch defaultChecked={user?.google2fa_enable} disabled />
                </Col> */}
      </Row>
      <Modal {...modalProps}>
        <Form {...formProps} layout="vertical">
          <Form.Item
            label={t('home.fields.oldPassword')}
            name="old_password"
            rules={[
              {
                required: true,
                message: t('home.tips.pleaseEnterOldPassword'),
              },
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label={t('home.fields.newPassword')}
            name="new_password"
            rules={[
              {
                required: true,
                message: t('home.tips.pleaseEnterNewPassword'),
              },
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label={t('home.fields.confirmPassword')}
            name={'confirm_password'}
            rules={[
              { required: true, message: t('home.fields.confirmPassword') },
              ({ getFieldValue }) => ({
                validator(_, value) {
                  if (!value || getFieldValue('new_password') === value) {
                    return Promise.resolve();
                  }
                  return Promise.reject(
                    new Error(t('home.error.passwordNotMatch'))
                  );
                },
              }),
            ]}
          >
            <Input.Password />
          </Form.Item>
          <Form.Item
            label={t('home.fields.oneTimePassword')}
            name="one_time_password"
            rules={[
              {
                required: user?.google2fa_enable,
                message: t('home.error.enterGoogleAuth'),
              },
            ]}
          >
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default HomePage;
