import { PlusOutlined } from '@ant-design/icons';
import { List, useForm, useModal } from '@refinedev/antd';
import { Button, Form, Input, Modal, Select, Tabs } from 'antd';
import { useApiUrl, useCustomMutation } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import { FC, useRef, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import IPTable from './IPTable';
import NameTable from './NameTable';

type TabKey = '提现持卡人姓名' | '代收实名' | '代收IP';

const MerchantBannedList: FC = () => {
  const { t } = useTranslation('merchant');
  const { modalProps, show, close } = useModal();
  const { formProps, form } = useForm();
  const { mutateAsync } = useCustomMutation();
  const apiUrl = useApiUrl();
  const [activeKey, setActiveKey] = useState<TabKey>('代收IP');

  // Store refetch functions from child components
  const refetchIPRef = useRef<() => void>(() => {});
  const refetchRealNameRef = useRef<() => void>(() => {});
  const refetchCardNameRef = useRef<() => void>(() => {});

  const handleAdd = async () => {
    await form?.validateFields();
    const type = form.getFieldValue('type');
    const name = form.getFieldValue('name');
    const resource = `banned/${type === 3 ? 'ip' : 'realname'}`;
    const values: Record<string, unknown> = { ...form?.getFieldsValue() };

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
      refetchIPRef.current();
    } else if (type === 1) {
      setActiveKey('代收实名');
      refetchRealNameRef.current();
    } else {
      setActiveKey('提现持卡人姓名');
      refetchCardNameRef.current();
    }

    close();
  };

  return (
    <>
      <Helmet>
        <title>{t('titles.bannedList')}</title>
      </Helmet>
      <List
        title={<ContentHeader title={t('titles.bannedList')} resource="merchants" />}
        headerButtons={() => (
          <Button onClick={show} icon={<PlusOutlined />}>
            {t('banned.add')}
          </Button>
        )}
      >
        <Tabs
          type="card"
          activeKey={activeKey}
          onChange={key => setActiveKey(key as TabKey)}
          destroyInactiveTabPane
          items={[
            {
              label: t('banned.withdrawCardHolder'),
              key: '提现持卡人姓名',
              children: (
                <NameTable
                  type={2}
                  onRefetchChange={refetch => {
                    refetchCardNameRef.current = refetch;
                  }}
                />
              ),
            },
            {
              label: t('banned.collectionRealName'),
              key: '代收实名',
              children: (
                <NameTable
                  type={1}
                  onRefetchChange={refetch => {
                    refetchRealNameRef.current = refetch;
                  }}
                />
              ),
            },
            {
              label: t('banned.collectionIp'),
              key: '代收IP',
              children: (
                <IPTable
                  onRefetchChange={refetch => {
                    refetchIPRef.current = refetch;
                  }}
                />
              ),
            },
          ]}
        />
      </List>
      <Modal
        {...modalProps}
        title={t('banned.add')}
        okText={t('actions.submit')}
        cancelText={t('actions.cancel')}
        onOk={handleAdd}
      >
        <Form {...formProps}>
          <Form.Item label={t('banned.category')} name="type" rules={[{ required: true }]}>
            <Select
              options={[
                { label: t('banned.collectionIp'), value: 3 },
                { label: t('banned.collectionRealName'), value: 1 },
                { label: t('banned.withdrawCardHolder'), value: 2 },
              ]}
            />
          </Form.Item>
          <Form.Item label={t('banned.ipOrName')} name="name" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label={t('banned.note')} name="note">
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
};

export default MerchantBannedList;
