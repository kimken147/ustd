import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import {
  Button,
  Col,
  ColProps,
  Create,
  Form,
  Input,
  Modal,
  Row,
  SaveButton,
  Select,
  Switch,
  useForm,
  useModal,
} from '@refinedev/antd';
import { useCreate } from '@refinedev/core';
import { useNavigate } from '@refinedev/react-router-v6';
import useSelector from 'hooks/useSelector';
import { Bank, User } from '@morgan-ustd/shared';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const SystemBankCardsCreate: FC = () => {
  const { t } = useTranslation('transaction');
  const title = t('titles.createSystemBankCard');

  const getColProps: (span: number) => ColProps = (span: number) => {
    return {
      xs: 24,
      sm: 12,
      lg: span,
    };
  };

  const [selectedKey, setSelectedKey] = useState<number | null>(null);
  const { selectProps: bankSelectProps } = useSelector<Bank>({
    resource: 'banks',
    valueField: 'name',
  });
  const { form } = useForm();
  const { form: userForm } = useForm();
  const { modalProps, show, close } = useModal();
  const { data: users } = useSelector<User>({
    resource: 'users',
    filters: [
      {
        field: 'role',
        value: 2,
        operator: 'eq',
      },
    ],
  });
  const navigate = useNavigate();
  const { mutateAsync: create } = useCreate();

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <Create
        title={title}
        footerButtons={() => (
          <>
            <SaveButton onClick={form.submit}>{t('actions.submit')}</SaveButton>
          </>
        )}
      >
        <Form
          layout="vertical"
          form={form}
          onFinish={async values => {
            await create({
              resource: 'system-bank-cards',
              values,
              successNotification: {
                message: t('messages.createSuccess'),
                type: 'success',
              },
            });
            navigate('/transaction/deposit/system-bank-cards');
          }}
        >
          <Form.List name={'system_bank_cards'} initialValue={[{}]}>
            {(fields, { add, remove }) => {
              return (
                <>
                  {fields.map(({ key, name }, index) => {
                    return (
                      <div key={key}>
                        <Row gutter={16}>
                          <Col {...getColProps(4)}>
                            <Form.Item
                              label={t('fields.bankName')}
                              name={[name, 'bank_name']}
                              rules={[{ required: true }]}
                            >
                              <Select {...bankSelectProps} />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(2)}>
                            <Form.Item
                              label={t('fields.bankProvince')}
                              name={[name, 'bank_province']}
                            >
                              <Input />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(2)}>
                            <Form.Item
                              label={t('fields.bankCity')}
                              name={[name, 'bank_city']}
                            >
                              <Input />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(4)}>
                            <Form.Item
                              label={t('fields.bankCardNumber')}
                              name={[name, 'bank_card_number']}
                              rules={[{ required: true }]}
                            >
                              <Input />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(3)}>
                            <Form.Item
                              label={t('fields.bankCardHolderName')}
                              name={[name, 'bank_card_holder_name']}
                              rules={[{ required: true }]}
                            >
                              <Input />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(4)}>
                            <Form.Item
                              label={t('fields.userName')}
                              shouldUpdate
                            >
                              {({ getFieldValue }) => {
                                return (
                                  <Input
                                    disabled
                                    value={getFieldValue([
                                      'system_bank_cards',
                                      name,
                                      'users',
                                    ])
                                      ?.map((user: any) => user.name)
                                      .join(', ')}
                                  />
                                );
                              }}
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(1)}>
                            <Form.Item label=" ">
                              <EditOutlined
                                onClick={() => {
                                  setSelectedKey(name);
                                  show();
                                }}
                              />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(3)}>
                            <Form.Item
                              label={t('fields.note')}
                              name={[name, 'note']}
                            >
                              <Input />
                            </Form.Item>
                          </Col>
                          <Col {...getColProps(1)}>
                            <Form.Item label=" ">
                              <Button
                                danger
                                onClick={() => remove(index)}
                                icon={<DeleteOutlined />}
                              />
                            </Form.Item>
                          </Col>
                        </Row>
                      </div>
                    );
                  })}
                  <Row gutter={16} align="middle">
                    <Form.Item>
                      <Button type="dashed" onClick={() => add()}>
                        {t('actions.createOne')}
                      </Button>
                    </Form.Item>
                  </Row>
                </>
              );
            }}
          </Form.List>
        </Form>
      </Create>
      <Modal
        {...modalProps}
        destroyOnClose
        onOk={() => {
          userForm.submit();
          close();
        }}
      >
        <Form
          form={userForm}
          onFinish={(values: any) => {
            if (values && selectedKey !== null) {
              form.setFieldValue(
                ['system_bank_cards', selectedKey, 'users'],
                values.users.map((user: any) => ({
                  id: user.user.id,
                  share_descendants: user.agent_enable,
                  name: user.user.name,
                }))
              );
            }
            userForm.resetFields();
          }}
        >
          <Form.List
            name={'users'}
            initialValue={[
              {
                agent_enable: false,
              },
            ]}
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
                            label={t('fields.agentLine')}
                            name={[name, 'agent_enable']}
                            valuePropName="checked"
                          >
                            <Switch
                              onChange={() =>
                                userForm.setFieldValue(
                                  ['users', name, 'user'],
                                  null
                                )
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
                                prev?.users[name]?.user?.id !==
                                  cur?.users[name]?.user?.id
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
                                    'user',
                                    'name',
                                  ])}
                                  onChange={value =>
                                    setFieldValue(
                                      ['users', name, 'user'],
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
    </>
  );
};

export default SystemBankCardsCreate;
