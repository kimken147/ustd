import { DeleteOutlined } from '@ant-design/icons';
import { CreateButton, List, useForm, useModal, useTable } from '@refinedev/antd';
import { useUpdate } from '@refinedev/core';
import {
  Button,
  Col,
  Divider,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Select,
  Switch,
} from 'antd';
import { ListPageLayout, Bank, User } from '@morgan-ustd/shared';
import ContentHeader from 'components/contentHeader';
import useSelector from 'hooks/useSelector';
import useUpdateModal from 'hooks/useUpdateModal';
import { SystemBankCard } from 'interfaces/systemBankCard';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const SystemBankCardList: FC = () => {
  const { t } = useTranslation('transaction');

  const { Select: UserSelect, data: users } = useSelector<User>({
    resource: 'users',
    filters: [{ field: 'role', value: 2, operator: 'eq' }],
  });

  const { selectProps: bankSelectProps } = useSelector<Bank>({
    resource: 'banks',
    valueField: 'name',
    labelField: 'name',
  });

  const {
    tableProps,
    searchFormProps,
    tableQuery: { data: tableData },
  } = useTable<SystemBankCard>({
    resource: 'system-bank-cards',
    syncWithLocation: true,
  });

  const systemBankCards = tableData?.data;

  const { mutateAsync: update } = useUpdate();

  const { modalProps, show } = useUpdateModal({
    formItems: [
      { label: t('fields.bankCardNumber'), name: 'bank_card_number', children: <Input /> },
      { label: t('fields.bankCardHolderName'), name: 'bank_card_holder_name', children: <Input /> },
      { label: t('fields.bankName'), name: 'bank_name', children: <Input /> },
      { label: t('fields.bankProvince'), name: 'bank_province', children: <Input /> },
      { label: t('fields.bankCity'), name: 'bank_city', children: <Input /> },
      { label: t('fields.quota'), name: 'balance', children: <InputNumber className="w-full" /> },
      { name: 'status', hidden: true },
      { label: t('fields.note'), name: 'note', children: <Input /> },
    ],
  });

  const { form: userForm } = useForm();
  const [selectedKey, setSelectedKey] = useState<number | null>(null);
  const {
    modalProps: userUpdateModalProps,
    show: showUpdateUserModal,
    close: closeUpdateUserModal,
  } = useModal();

  const columnDeps: ColumnDependencies = {
    t,
    show,
    setSelectedKey,
    showUpdateUserModal,
    update: update as ColumnDependencies['update'],
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('titles.systemBankCardList')}</title>
      </Helmet>
      <List
        title={<ContentHeader title={t('titles.systemBankCardList')} resource="deposit" />}
        headerButtons={<CreateButton>{t('titles.createSystemBankCard')}</CreateButton>}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.bankCardKeywordFilter')} name="name_or_username">
                <Select {...bankSelectProps} allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.status')} name="statuses[]">
                <Select
                  options={[
                    { label: t('status.onShelf'), value: 1 },
                    { label: t('status.offShelf'), value: 0 },
                  ]}
                  allowClear
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.userFilter')} name="user_id">
                <UserSelect allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('fields.note')} name="note">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />

        <Modal {...modalProps} />
        <Modal
          {...userUpdateModalProps}
          destroyOnClose
          onOk={() => userForm.submit()}
        >
          <Form
            form={userForm}
            onFinish={async (values: any) => {
              await update({
                resource: 'system-bank-cards',
                id: selectedKey!,
                values: {
                  id: selectedKey,
                  users: values.users.map((user: { id: number; agent_enable: boolean; name: string }) => ({
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
              name="users"
              initialValue={systemBankCards
                ?.find(card => card.id === selectedKey)
                ?.users.map(user => ({
                  ...user,
                  agent_enable: user.share_descendants,
                }))}
            >
              {(fields, { add, remove }) => (
                <>
                  {fields.map(({ key, name }) => (
                    <Row gutter={8} key={key}>
                      <Col span={6}>
                        <Form.Item
                          label={t('fields.province')}
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
                          label={t('fields.group')}
                          shouldUpdate={(prev, cur) =>
                            prev?.users?.[name]?.agent_enable !== cur?.users?.[name]?.agent_enable ||
                            prev?.users?.[name]?.id !== cur?.users?.[name]?.id
                          }
                        >
                          {({ getFieldValue, setFieldValue }) => {
                            const agentEnable = getFieldValue(['users', name, 'agent_enable']);
                            return (
                              <Select
                                value={getFieldValue(['users', name, 'name'])}
                                onChange={value =>
                                  setFieldValue(['users', name], users?.find(user => user.id === value))
                                }
                                options={users
                                  ?.filter(user => (agentEnable ? user.agent_enable : true))
                                  .map(user => ({ label: user.name, value: user.id }))}
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
                  ))}
                  <Form.Item>
                    <Button type="dashed" onClick={() => add({ agent_enable: false, user: null })}>
                      {t('actions.createOne')}
                    </Button>
                  </Form.Item>
                </>
              )}
            </Form.List>
          </Form>
        </Modal>
      </List>
    </>
  );
};

export default SystemBankCardList;
