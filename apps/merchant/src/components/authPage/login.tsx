import { LoginFormTypes, useApiUrl, useCustomMutation, useLogin, useTranslate } from '@refinedev/core';
import { Form, Input, Button } from 'antd';
import { UserOutlined, LockOutlined, SafetyOutlined } from '@ant-design/icons';
import { FC, useState } from 'react';

const LoginPage: FC = () => {
  const [form] = Form.useForm<LoginFormTypes>();
  const t = useTranslate();
  const apiUrl = useApiUrl();
  const { mutate: login, isPending: isPendingLogin } = useLogin();
  const { mutateAsync, mutation: preLoginMutation } = useCustomMutation<IPreLoginRes>();
  const isPreLoginLoading = preLoginMutation.isPending;
  const isLoading = isPendingLogin || isPreLoginLoading;
  const [isGoogleAuthOpen, setGoogleAuth] = useState<boolean | null>(null);

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        background: 'linear-gradient(160deg, #0a1628 0%, #152a45 40%, #1a3a5c 70%, #0d2137 100%)',
        position: 'relative',
        overflow: 'hidden',
      }}
    >
      {/* Decorative orbs */}
      <div
        style={{
          position: 'absolute',
          width: 600,
          height: 600,
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(0,144,229,0.08) 0%, transparent 70%)',
          top: '-15%',
          right: '-10%',
          pointerEvents: 'none',
        }}
      />
      <div
        style={{
          position: 'absolute',
          width: 500,
          height: 500,
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(0,100,200,0.06) 0%, transparent 70%)',
          bottom: '-15%',
          left: '-8%',
          pointerEvents: 'none',
        }}
      />

      {/* Logo — on dark background */}
      <div style={{ marginBottom: 32, position: 'relative', zIndex: 1 }}>
        <img
          src={process.env.REACT_APP_LOGO_SRC}
          alt="Logo"
          style={{ height: 52, objectFit: 'contain' }}
        />
      </div>

      {/* Card */}
      <div
        className="login-dark"
        style={{
          width: '100%',
          maxWidth: 400,
          margin: '0 16px',
          background: 'rgba(255,255,255,0.07)',
          backdropFilter: 'blur(20px)',
          WebkitBackdropFilter: 'blur(20px)',
          borderRadius: 16,
          border: '1px solid rgba(255,255,255,0.1)',
          padding: '40px 36px 36px',
          position: 'relative',
          zIndex: 1,
        }}
      >
        <Form<LoginFormTypes>
          form={form}
          layout="vertical"
          onFinish={async (values) => {
            if (isGoogleAuthOpen) {
              login(values);
              return;
            }
            const res = await mutateAsync({
              url: `${apiUrl}/pre-login`,
              method: 'post',
              values,
            });
            if (!res.data.google2fa_enable) {
              login({ ...values, googleAuth: '123' });
            }
            setGoogleAuth(res.data.google2fa_enable);
          }}
          requiredMark={false}
          initialValues={{ remember: false }}
          size="large"
        >
          <Form.Item
            name="username"
            rules={[{ required: true, message: t('login.usernameRequired', '请输入帐号') }]}
          >
            <Input
              prefix={<UserOutlined style={{ color: 'rgba(255,255,255,0.4)' }} />}
              placeholder={t('login.username', '帐号')}
              style={{ borderRadius: 10, height: 46 }}
            />
          </Form.Item>

          <Form.Item
            name="password"
            rules={[{ required: true, message: t('login.passwordRequired', '请输入密码') }]}
          >
            <Input.Password
              prefix={<LockOutlined style={{ color: 'rgba(255,255,255,0.4)' }} />}
              placeholder={t('login.password', '密码')}
              style={{ borderRadius: 10, height: 46 }}
            />
          </Form.Item>

          <Form.Item
            name="googleAuth"
            rules={[{ required: isGoogleAuthOpen === true }]}
            hidden={isGoogleAuthOpen === null || isGoogleAuthOpen === false}
          >
            <Input
              prefix={<SafetyOutlined style={{ color: 'rgba(255,255,255,0.4)' }} />}
              placeholder={t('login.securityCode', '安全码')}
              style={{ borderRadius: 10, height: 46 }}
            />
          </Form.Item>

          <Form.Item style={{ marginTop: 8, marginBottom: 0 }}>
            <Button
              type="primary"
              htmlType="submit"
              loading={isLoading}
              block
              style={{
                height: 46,
                borderRadius: 10,
                fontWeight: 600,
                fontSize: 16,
                background: '#0090e5',
                border: 'none',
                boxShadow: '0 4px 16px rgba(0,144,229,0.4)',
              }}
            >
              {t('login.submit', '登录')}
            </Button>
          </Form.Item>
        </Form>
      </div>
    </div>
  );
};

export default LoginPage;
