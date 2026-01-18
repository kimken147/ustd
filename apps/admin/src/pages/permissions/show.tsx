import { EditOutlined } from '@ant-design/icons';
import {
  Button,
  DateField,
  Descriptions,
  Input,
  Modal,
  Show,
  Space,
  Spin,
  Switch,
  TextField,
  useModal,
} from '@pankod/refine-antd';
import { useApiUrl, useShow } from '@pankod/refine-core';
import { useLocation, useNavigate } from '@pankod/refine-react-router-v6';
import EditableForm from 'components/EditableFormItem';
import PermissionCheckGroup from 'components/permissionCheckGroup';
import useUpdateModal from 'hooks/useUpdateModal';
import { SubAccount } from 'interfaces/subAccount';
import { Format } from '@morgan-ustd/shared';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const SubAccountShow: FC = () => {
  const { t } = useTranslation('permission');
  const apiUrl = useApiUrl();
  const navigate = useNavigate();
  const { state } = useLocation();
  const { queryResult } = useShow<SubAccount>();
  const { data, isLoading } = queryResult;
  const record = {
    ...(state as SubAccount),
    ...data?.data,
  };
  const { modalProps, show, close } = useModal({
    modalProps: {
      okText: t('show.modal.submit'),
      cancelText: t('show.modal.cancel'),
      destroyOnClose: true,
      title: t('show.modal.title'),
    },
  });
  const { Modal: UpdateModal } = useUpdateModal();
  const [ids, setIds] = useState(record?.permissions?.map(per => per.id) ?? []);
  if (isLoading) return <Spin />;
  return (
    <>
      <Show title={t('show.title')} headerButtons={() => null}>
        <Helmet>
          <title>{t('show.title')}</title>
        </Helmet>
        <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered>
          <Descriptions.Item label={t('show.fields.subAccountName')}>
            <EditableForm name={'name'} id={record.id}>
              <Input defaultValue={record.name} />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.loginAccount')}>
            <EditableForm name={'username'} id={record.id}>
              <Input defaultValue={record.username} />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.accountStatus')}>
            <Switch
              checked={!!record.status}
              onChange={checked =>
                UpdateModal.confirm({
                  title: t('show.messages.confirmModifyAccountStatus'),
                  id: record.id,
                  values: {
                    status: +checked,
                  },
                })
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.googleAuthStatus')}>
            <Switch
              checked={record.google2fa_enable}
              onChange={checked =>
                UpdateModal.confirm({
                  title: t('show.messages.confirmModifyGoogleAuthStatus'),
                  id: record.id,
                  values: {
                    google2fa_enable: +checked,
                  },
                })
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.lastLoginTime')}>
            {record.last_login_at ? (
              <DateField value={record.last_login_at} format={Format} />
            ) : (
              t('show.fields.none')
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.ip')}>
            <TextField
              value={record.last_login_ipv4 ?? t('show.fields.none')}
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.permissionSettings')}>
            <Space wrap>
              {record.permissions.map(per => (
                <TextField key={per.id} code value={per.name} />
              ))}
              <EditOutlined
                onClick={() => show()}
                style={{
                  color: '#6eb9ff',
                }}
              />
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.password')}>
            <Space>
              <Button
                danger
                type="primary"
                onClick={() =>
                  UpdateModal.confirm({
                    title: t('show.messages.confirmResetPassword'),
                    id: record.id,
                    customMutateConfig: {
                      url: `${apiUrl}/sub-accounts/${record.id}/password-resets`,
                      method: 'post',
                    },
                    onSuccess(data) {
                      navigate(`/sub-accounts/show/${data?.id}`, {
                        state: {
                          ...(state as SubAccount),
                          ...record,
                          ...data,
                        },
                        replace: true,
                      });
                    },
                  })
                }
              >
                {t('show.actions.resetPassword')}
              </Button>
              {record.password ? (
                <TextField value={record.password} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('show.fields.googleAuthSecret')}>
            <Space>
              <Button
                danger
                type="primary"
                onClick={() =>
                  UpdateModal.confirm({
                    title: t('show.messages.confirmResetSecret'),
                    id: record.id,
                    customMutateConfig: {
                      url: `${apiUrl}/sub-accounts/${record.id}/google2fa-secret-resets`,
                      method: 'post',
                    },
                    onSuccess(data) {
                      navigate(`/sub-accounts/show/${data?.id}`, {
                        state: {
                          ...(state as SubAccount),
                          ...record,
                          ...data,
                        },
                        replace: true,
                      });
                    },
                  })
                }
              >
                {t('show.actions.resetAuthCode')}
              </Button>
              {record.google2fa_secret ? (
                <TextField value={record.google2fa_secret} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          {record.google2fa_qrcode ? (
            <Descriptions.Item label={t('show.fields.googleAuthQRCode')}>
              <div
                dangerouslySetInnerHTML={{
                  __html: record?.google2fa_qrcode,
                }}
              />
            </Descriptions.Item>
          ) : null}
        </Descriptions>
      </Show>
      <Modal
        {...modalProps}
        onOk={() =>
          UpdateModal.confirm({
            title: t('show.messages.confirmModifyPermission'),
            id: record.id,
            values: {
              permissions: ids,
              id: record.id,
            },
            onSuccess() {
              close();
            },
          })
        }
      >
        <PermissionCheckGroup
          defaultIds={record.permissions.map(per => per.id)}
          onChange={ids => setIds(ids)}
        />
      </Modal>
    </>
  );
};

export default SubAccountShow;
