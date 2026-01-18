import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import {
  Button,
  Col,
  CreateButton,
  DeleteButton,
  Divider,
  Form,
  Input,
  InputNumber,
  List,
  Modal,
  Row,
  Select,
  Space,
  Switch,
  Table,
  TextField,
  useForm,
  useModal,
} from '@pankod/refine-antd';
import { useUpdate } from '@pankod/refine-core';
import ContentHeader from 'components/contentHeader';
import dayjs from 'dayjs';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Bank, User } from '@morgan-ustd/shared';
import { SystemBankCard } from 'interfaces/systemBankCard';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const SystemBankCardList: FC = () => {
  const { t } = useTranslation('transaction');

  const { Select: UserSelect, data: users } = useSelector<User>({
    resource: 'users',
    filters: [
      {
        field: 'role',
        value: 2,
        operator: 'eq',
      },
    ],
  });
  const { selectProps: bankSelectProps } = useSelector<Bank>({
    resource: 'banks',
    valueField: 'name',
    labelField: 'name',
  });
  const {
    Form: FilterForm,
    tableProps,
    data: systemBankCards,
  } = useTable<SystemBankCard>({
    resource: 'system-bank-cards',
    formItems: [
      {
        label: t('fields.bankCardKeywordFilter'),
        name: 'name_or_username',
        children: <Select {...bankSelectProps} />,
      },
      {
        label: t('fields.status'),
        name: 'statuses[]',
        children: (
          <Select
            options={[
              {
                label: t('status.onShelf'),
                value: 1,
              },
              {
                label: t('status.offShelf'),
                value: 0,
              },
            ]}
          />
        ),
      },
      {
        label: t('fields.userFilter'),
        name: 'user_id',
        children: <UserSelect />,
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
    ],
    columns: [
      {
        title: t('fields.status'),
        dataIndex: 'status',
        render(value, record, index) {
          return (
            <TextField
              value={value ? t('status.onShelf') : t('status.offShelf')}
              className={value ? 'text-[#16A34A]' : 'text-[#FF4D4F]'}
            />
          );
        },
      },
      {
        title: t('fields.bankCardNumber'),
        dataIndex: 'bank_card_number',
        render(value, record, index) {
          return <TextField value={value} />;
        },
      },
      {
        title: t('fields.bankCardHolderName'),
        dataIndex: 'bank_card_holder_name',
        render(value, record, index) {
          return <TextField value={value} />;
        },
      },
      {
        title: t('fields.bankName'),
        dataIndex: 'bank_name',
        render(value, record, index) {
          return <TextField value={value} />;
        },
      },
      {
        title: t('fields.bankProvince'),
        dataIndex: 'bank_province',
        render(value, record, index) {
          return <TextField value={value} />;
        },
      },
      {
        title: t('fields.bankCity'),
        dataIndex: 'bank_city',
        render(value, record, index) {
          return <TextField value={value} />;
        },
      },
      {
        title: t('fields.quota'),
        dataIndex: 'balance',
        render(value, record, index) {
          return (
            <Space>
              <TextField value={value} />
              <Button
                icon={<EditOutlined className="text-[#6eb9ff]" />}
                onClick={() => {
                  show({
                    title: t('actions.editQuota'),
                    filterFormItems: ['balance'],
                    id: record.id,
                    initialValues: {
                      balance: record.balance,
                    },
                  });
                }}
              />
            </Space>
          );
        },
      },
      {
        title: t('fields.createdAt'),
        dataIndex: 'created_at',
        render(value, record, index) {
          return dayjs(value).format('YYYY-MM-DD HH:mm:ss');
        },
      },
      {
        title: t('fields.userFullOpen'),
        dataIndex: 'users',
        render(value, record, index) {
          return (
            <Space>
              <TextField
                value={value
                  ?.map((item: any) => {
                    if (item.share_descendants) {
                      return `${item.name}(${t('info.fullLineOpen')})`;
                    }
                    return item.name;
                  })
                  .join(', ')}
              />
              <Button
                icon={<EditOutlined className="text-[#6eb9ff]" />}
                onClick={() => {
                  setSelectedKey(record.id);
                  showUpdateUserModal();
                }}
              />
            </Space>
          );
        },
      },
      {
        title: t('fields.note'),
        dataIndex: 'note',
        render(value, record, index) {
          return (
            <Space>
              <TextField value={value} />
              <Button
                icon={<EditOutlined className="text-[#6eb9ff]" />}
                onClick={() => {
                  show({
                    title: t('actions.editNote'),
                    filterFormItems: ['note'],
                    id: record.id,
                    initialValues: {
                      note: record.note,
                    },
                  });
                }}
              />
            </Space>
          );
        },
      },
      {
        title: t('actions.operation'),
        dataIndex: 'status',
        render(value, record) {
          return (
            <Space>
              <Button
                onClick={() => {
                  if (value) {
                    Modal.confirm({
                      title: t('actions.confirmOffShelf'),
                      onOk: async () => {
                        await update({
                          resource: 'system-bank-cards',
                          id: record.id,
                          values: {
                            status: 0,
                            id: record.id,
                          },
                          successNotification: {
                            message: t('messages.offShelfSuccess'),
                            type: 'success',
                          },
                        });
                      },
                    });
                  } else {
                    show({
                      title: value
                        ? t('actions.offShelf')
                        : t('actions.onShelf'),
                      filterFormItems: ['balance', 'status'],
                      initialValues: {
                        balance: record.balance,
                        status: value ? 0 : 1,
                      },
                      id: record.id,
                    });
                  }
                }}
              >
                {value ? t('actions.offShelf') : t('actions.onShelf')}
              </Button>
              <DeleteButton>{t('actions.delete')}</DeleteButton>
            </Space>
          );
        },
      },
    ],
  });

  const { mutateAsync: update } = useUpdate();

  const { modalProps, show } = useUpdateModal({
    formItems: [
      {
        label: t('fields.bankCardNumber'),
        name: 'bank_card_number',
        children: <Input />,
      },
      {
        label: t('fields.bankCardHolderName'),
        name: 'bank_card_holder_name',
        children: <Input />,
      },
      {
        label: t('fields.bankName'),
        name: 'bank_name',
        children: <Input />,
      },
      {
        label: t('fields.bankProvince'),
        name: 'bank_province',
        children: <Input />,
      },
      {
        label: t('fields.bankCity'),
        name: 'bank_city',
        children: <Input />,
      },
      {
        label: t('fields.quota'),
        name: 'balance',
        children: <InputNumber className="w-full" />,
      },
      {
        name: 'status',
        hidden: true,
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
    ],
  });

  const { form: userForm } = useForm();

  const [selectedKey, setSelectedKey] = useState<number | null>(null);
  const {
    modalProps: userUpdateModalProps,
    show: showUpdateUserModal,
    close: closeUpdateUserModal,
  } = useModal();

  return (
    <>
      <Helmet>
        <title>{t('titles.systemBankCardList')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader
            title={t('titles.systemBankCardList')}
            resource="deposit"
          />
        }
        headerButtons={
          <>
            <CreateButton>{t('titles.createSystemBankCard')}</CreateButton>
          </>
        }
      >
        <FilterForm />
        <Divider />
        <Table {...tableProps} />
        <Modal {...modalProps} />
        <Modal
          {...userUpdateModalProps}
          destroyOnClose
          onOk={() => {
            userForm.submit();
          }}
        >
          <Form
            form={userForm}
            onFinish={async (values: any) => {
              await update({
                resource: 'system-bank-cards',
                id: selectedKey!,
                values: {
                  id: selectedKey,
                  users: values.users.map((user: any) => ({
                    id: user.id,
                    share_descendants: user.agent_enable,
                    name: user.name,
                  })),
                },
                successNotification: {
                  message: t('messages.updateSuccess'),
                  type: 'success',
                },
              });
              userForm.resetFields();
              closeUpdateUserModal();
            }}
          >
            <Form.List
              name={'users'}
              initialValue={systemBankCards
                ?.find(card => card.id === selectedKey)
                ?.users.map(user => ({
                  ...user,
                  agent_enable: user.share_descendants,
                }))}
            >
              {(fields, { add, remove }) => {
                const newItem = {
                  agent_enable: false,
                  user: null,
                };
                return (
                  <>
                    {fields.map(({ key, name }) => {
                      return (
                        <Row gutter={8} key={key}>
                          <Col span={6}>
                            <Form.Item
                              label={t('fields.province')} // 代理线 可自行調整翻譯
                              name={[name, 'agent_enable']}
                              valuePropName="checked"
                            >
                              <Switch
                                onChange={checked =>
                                  userForm.setFieldValue(['users', name], {
                                    agent_enable: checked,
                                    id: null,
                                  })
                                }
                              />
                            </Form.Item>
                          </Col>
                          <Col span={14}>
                            <Form.Item
                              label={t('fields.group')} // 码商
                              shouldUpdate={(prev, cur) => {
                                return (
                                  prev?.users[name]?.agent_enable !==
                                    cur?.users[name]?.agent_enable ||
                                  prev?.users[name]?.id !== cur?.users[name]?.id
                                );
                              }}
                            >
                              {({ getFieldValue, setFieldValue }) => {
                                const agentEnable = getFieldValue([
                                  'users',
                                  name,
                                  'agent_enable',
                                ]);
                                return (
                                  <Select
                                    value={getFieldValue([
                                      'users',
                                      name,
                                      'name',
                                    ])}
                                    onChange={value =>
                                      setFieldValue(
                                        ['users', name],
                                        users?.find(user => user.id === value)
                                      )
                                    }
                                    options={users
                                      ?.filter(user =>
                                        agentEnable ? user.agent_enable : true
                                      )
                                      .map(user => ({
                                        label: user.name,
                                        value: user.id,
                                      }))}
                                  />
                                );
                              }}
                            </Form.Item>
                          </Col>
                          <Col span={4}>
                            <Form.Item label=" " colon={false}>
                              <DeleteOutlined onClick={() => remove(name)} />
                            </Form.Item>
                          </Col>
                        </Row>
                      );
                    })}
                    <Form.Item>
                      <Button type="dashed" onClick={() => add(newItem)}>
                        {t('actions.createOne')}
                      </Button>
                    </Form.Item>
                  </>
                );
              }}
            </Form.List>
          </Form>
        </Modal>
      </List>
    </>
  );
};

export default SystemBankCardList;
