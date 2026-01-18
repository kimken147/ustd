import { ExportOutlined } from '@ant-design/icons';
import {
  DeleteButton,
  Divider,
  EditButton,
  Input,
  List,
  Modal,
  Space,
  Table,
  TableColumnProps,
  Typography,
  Form as AntdForm,
  useModal,
  useForm,
  CreateButton,
  Button,
} from '@pankod/refine-antd';
import { useApiUrl, useCreate, useUpdate } from '@pankod/refine-core';
import { getToken } from 'authProvider';
import ContentHeader from 'components/contentHeader';
import { generateFilter } from 'dataProvider';
import useTable from 'hooks/useTable';
import { Bank } from '@morgan-ustd/shared';
import queryString from 'query-string';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const BankList: FC = () => {
  const { t } = useTranslation('systemSettings');
  const apiUrl = useApiUrl();
  const [action, setAction] = useState<'create' | 'edit'>('edit');
  const [current, setCurrent] = useState<Bank>();
  const { form } = useForm();
  const { modalProps, show, close } = useModal();
  const { mutateAsync: update } = useUpdate();
  const { mutateAsync: create } = useCreate();
  const { tableProps, Form, filters } = useTable<Bank>({
    resource: 'banks',
    formItems: [
      {
        label: t('bank.fields.bankName'),
        name: 'name',
        children: <Input />,
      },
    ],
  });
  const columns: TableColumnProps<Bank>[] = [
    {
      title: t('bank.fields.bankName'),
      dataIndex: 'name',
    },
    {
      title: t('bank.actions.edit'),
      render(value, record, index) {
        return (
          <Space>
            <EditButton
              onClick={() => {
                setCurrent(record);
                setAction('edit');
                show();
              }}
            >
              {t('bank.actions.edit')}
            </EditButton>
            <DeleteButton
              confirmCancelText={t('bank.actions.cancel')}
              confirmOkText={t('bank.actions.confirm')}
              confirmTitle={t('bank.messages.confirmDelete')}
              resource="banks"
              recordItemId={record.id.toString()}
              successNotification={{
                message: t('bank.messages.deleteSuccess'),
                type: 'success',
              }}
            >
              {t('bank.actions.delete')}
            </DeleteButton>
          </Space>
        );
      },
    },
  ];
  return (
    <>
      <Helmet>
        <title>{t('bank.title')}</title>
      </Helmet>
      <List
        title={
          <ContentHeader title={t('bank.title')} resource="feature-toggles" />
        }
        headerButtons={
          <>
            <CreateButton
              onClick={() => {
                setAction('create');
                show();
              }}
            >
              {t('bank.actions.create')}
            </CreateButton>
            <Button
              icon={<ExportOutlined />}
              onClick={async () => {
                const url = `${apiUrl}/bank-report?${queryString.stringify(
                  generateFilter(filters)
                )}&token=${getToken()}`;
                window.open(url);
              }}
            >
              {t('bank.actions.export')}
            </Button>
          </>
        }
      >
        <Form />
        <Divider />
        <Table {...tableProps} columns={columns} />
      </List>
      <Modal
        {...modalProps}
        title={t('bank.actions.edit')}
        destroyOnClose
        onOk={form.submit}
      >
        {action === 'edit' && (
          <Typography.Paragraph className="text-[#FF4D4F]">
            {t('bank.messages.bankNameWarning')}
          </Typography.Paragraph>
        )}
        <AntdForm
          initialValues={current}
          form={form}
          onFinish={async values => {
            if (action === 'edit') {
              await update({
                resource: 'banks',
                id: current?.id ?? 0,
                values,
                successNotification: {
                  message: t('bank.messages.editSuccess'),
                  type: 'success',
                },
              });
            } else {
              await create({
                resource: 'banks',
                values,
                successNotification: {
                  message: t('bank.messages.createSuccess'),
                  type: 'success',
                },
              });
            }
            close();
          }}
        >
          <AntdForm.Item
            label={t('bank.fields.bankName')}
            name={'name'}
            rules={[{ required: true }]}
          >
            <Input />
          </AntdForm.Item>
        </AntdForm>
      </Modal>
    </>
  );
};

export default BankList;
