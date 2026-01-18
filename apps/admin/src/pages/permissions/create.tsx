import {
  Create,
  SaveButton,
  useForm,
} from '@refinedev/antd';
import {
  Divider,
  Form,
  Input,
} from 'antd';
import { useCreate, useNavigation } from '@refinedev/core';
import { useNavigate } from 'react-router-dom';
import PermissionCheckGroup from 'components/permissionCheckGroup';
import { SubAccount } from 'interfaces/subAccount';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const SubAccountCreate: FC = () => {
  const { t } = useTranslation('permission');
  const { form } = useForm();
  const { mutateAsync: create } = useCreate<SubAccount>();
  const { showUrl } = useNavigation();
  const navigate = useNavigate();
  return (
    <>
      <Helmet>
        <title>{t('create.title')}</title>
      </Helmet>
      <Create
        title={t('create.title')}
        footerButtons={() => (
          <SaveButton onClick={form.submit}>
            {t('create.actions.submit')}
          </SaveButton>
        )}
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={async values => {
            const res = await create({
              values,
              resource: 'sub-accounts',
              successNotification: {
                type: 'success',
                message: t('create.messages.createSuccess'),
              },
            });
            navigate(
              {
                pathname: showUrl('sub-accounts', res.data.id),
              },
              {
                state: res.data,
              }
            );
          }}
        >
          <Form.Item
            label={t('create.fields.subAccountName')}
            name={'name'}
            rules={[{ required: true }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label={t('create.fields.loginAccount')}
            name={'username'}
            rules={[
              { required: true },
              () => ({
                validator(_, value) {
                  if (/^[A-Za-z][A-Za-z0-9_]{4,10}$/.test(value)) {
                    return Promise.resolve();
                  } else {
                    return Promise.reject(
                      new Error(t('create.fields.accountValidation'))
                    );
                  }
                },
              }),
            ]}
          >
            <Input />
          </Form.Item>
          <Divider />
          <Form.Item
            label={t('create.fields.permissionSettings')}
            name={'permissions'}
            rules={[{ required: true }]}
          >
            <PermissionCheckGroup />
          </Form.Item>
        </Form>
      </Create>
    </>
  );
};

export default SubAccountCreate;
