import { EditOutlined, PlusOutlined } from '@ant-design/icons';
import {
  Button,
  Divider,
  Form,
  Input,
  List,
  Modal,
  Select,
  Space,
  Tabs,
  TextField,
  useForm,
  useModal,
} from '@refinedev/antd';
import { useApiUrl, useCustomMutation } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import dayjs from 'dayjs';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Banned } from 'interfaces/banned';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const useIPComponent = () => {
  const { t } = useTranslation('merchant');
  const resource = 'banned/ip';
  const { Form, Table, refetch } = useTable<Banned>({
    resource,
    formItems: [
      { label: t('banned.collectionIp'), name: 'ipv4', children: <Input /> },
    ],
  });
  const { show, Modal } = useUpdateModal({
    formItems: [
      {
        label: t('banned.note'),
        name: 'note',
        children: <Input />,
        rules: [{ required: true }],
      },
    ],
    resource,
  });

  const IPComponent: FC = () => (
    <>
      <Form />
      <Divider />
      <h2>{t('banned.blockedIpList')}</h2>
      <Table>
        <Table.Column dataIndex={'ipv4'} title={t('banned.collectionIp')} />
        <Table.Column<Banned>
          dataIndex={'note'}
          title={t('banned.note')}
          render={(value, record) => (
            <Space>
              <TextField value={value} />
              <EditOutlined
                style={{ color: '#6eb9ff' }}
                onClick={() =>
                  show({
                    title: t('banned.editNote'),
                    id: record.id,
                    filterFormItems: ['note'],
                    initialValues: { note: value },
                    resource,
                  })
                }
              />
            </Space>
          )}
        />
        <Table.Column
          title={t('banned.blockTime')}
          dataIndex={'created_at'}
          render={value => dayjs(value).format('YYYY-MM-DD HH:mm:ss')}
        />
        <Table.Column<Banned>
          title={t('actions.delete')}
          render={(_, record) => (
            <Button
              danger
              onClick={() =>
                Modal.confirm({
                  title: t('banned.deleteConfirm'),
                  id: record.ipv4,
                  resource,
                  values: { type: 1, ipv4: record.ipv4 },
                  mode: 'delete',
                })
              }
            >
              {t('actions.delete')}
            </Button>
          )}
        />
      </Table>
      <Modal />
    </>
  );

  return { IPComponent, refetch };
};

const useNameComponent = ({ type }: { type: number }) => {
  const { t } = useTranslation('merchant');
  const resource = 'banned/realname';
  const { Form, Table, refetch } = useTable<Banned>({
    resource,
    formItems: [
      { label: t('banned.realName'), name: 'realname', children: <Input /> },
    ],
    filters: [{ field: 'type', value: type, operator: 'eq' }],
  });
  const { show, Modal } = useUpdateModal({
    formItems: [
      {
        label: t('banned.note'),
        name: 'note',
        children: <Input />,
        rules: [{ required: true }],
      },
    ],
    resource,
  });

  const NameComponent: FC = () => (
    <>
      <Form />
      <Divider />
      <h2>
        {type === 1
          ? t('banned.blockedRealNameList')
          : t('banned.blockedCardHolderList')}
      </h2>
      <Table>
        <Table.Column
          dataIndex={'realname'}
          title={
            type === 1
              ? t('banned.blockedRealName')
              : t('banned.blockedCardHolder')
          }
        />
        <Table.Column<Banned>
          dataIndex={'note'}
          title={t('banned.note')}
          render={(value, record) => (
            <Space>
              <TextField value={value} />
              <EditOutlined
                style={{ color: '#6eb9ff' }}
                onClick={() =>
                  show({
                    title: t('banned.editNote'),
                    id: record.id,
                    filterFormItems: ['note'],
                    initialValues: { note: value },
                    resource,
                  })
                }
              />
            </Space>
          )}
        />
        <Table.Column
          title={t('banned.blockTime')}
          dataIndex={'created_at'}
          render={value => dayjs(value).format('YYYY-MM-DD HH:mm:ss')}
        />
        <Table.Column<Banned>
          title={t('actions.delete')}
          render={(_, record) => (
            <Button
              danger
              onClick={() =>
                Modal.confirm({
                  title: t('banned.deleteConfirm'),
                  id: record.realname,
                  resource,
                  values: { type, realname: record.realname },
                  mode: 'delete',
                })
              }
            >
              {t('actions.delete')}
            </Button>
          )}
        />
      </Table>
      <Modal />
    </>
  );

  return { NameComponent, refetch };
};

const MerchantBannedList: FC = () => {
  const { t } = useTranslation('merchant');
  const { modalProps, show, close } = useModal();
  const { formProps, form } = useForm();
  const { mutateAsync } = useCustomMutation();
  const apiUrl = useApiUrl();
  const [activeKey, setActiveKey] = useState<
    '提现持卡人姓名' | '代收实名' | '代收IP'
  >('代收IP');

  const { IPComponent, refetch: refetchIP } = useIPComponent();
  const { NameComponent: RealNameComponent, refetch: refetchRealName } =
    useNameComponent({ type: 1 });
  const { NameComponent: CardNameComponent, refetch: refetchCardName } =
    useNameComponent({ type: 2 });

  return (
    <>
      <Helmet>
        <title>{t('titles.bannedList')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader title={t('titles.bannedList')} resource="merchants" />
        }
        headerButtons={() => (
          <Button onClick={show} icon={<PlusOutlined />}>
            {t('banned.add')}
          </Button>
        )}
      >
        <Tabs
          type="card"
          activeKey={activeKey}
          onChange={key => setActiveKey(key as any)}
          destroyInactiveTabPane
          items={[
            {
              label: t('banned.withdrawCardHolder'),
              key: '提现持卡人姓名',
              children: <CardNameComponent />,
            },
            {
              label: t('banned.collectionRealName'),
              key: '代收实名',
              children: <RealNameComponent />,
            },
            {
              label: t('banned.collectionIp'),
              key: '代收IP',
              children: <IPComponent />,
            },
          ]}
        />
      </List>
      <Modal
        {...modalProps}
        title={t('banned.add')}
        okText={t('actions.submit')}
        cancelText={t('actions.cancel')}
        onOk={async () => {
          await form?.validateFields();
          const type = form.getFieldValue('type');
          const name = form.getFieldValue('name');
          const resource = `banned/${type === 3 ? 'ip' : 'realname'}`;
          const values: any = { ...form?.getFieldsValue() };
          if (type === 3) {
            values.ipv4 = name;
            values.type = 1;
          } else {
            values.realname = name;
            values.type = type;
          }
          await mutateAsync({
            values,
            url: `${apiUrl}/${resource}`,
            method: 'post',
            successNotification: {
              message: t('messages.addSuccess'),
              type: 'success',
            },
            errorNotification: {
              message: t('messages.addError'),
              type: 'error',
            },
          });
          form.resetFields();
          if (type === 3) {
            setActiveKey('代收IP');
            refetchIP();
          } else if (type === 1) {
            setActiveKey('代收实名');
            refetchRealName();
          } else {
            setActiveKey('提现持卡人姓名');
            refetchCardName();
          }
          close();
        }}
      >
        <Form {...formProps}>
          <Form.Item
            label={t('banned.category')}
            name={'type'}
            rules={[{ required: true }]}
          >
            <Select
              options={[
                { label: t('banned.collectionIp'), value: 3 },
                { label: t('banned.collectionRealName'), value: 1 },
                { label: t('banned.withdrawCardHolder'), value: 2 },
              ]}
            />
          </Form.Item>
          <Form.Item
            label={t('banned.ipOrName')}
            name={'name'}
            rules={[{ required: true }]}
          >
            <Input />
          </Form.Item>
          <Form.Item label={t('banned.note')} name={'note'}>
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
};

export default MerchantBannedList;
