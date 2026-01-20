import { ExportOutlined } from '@ant-design/icons';
import { CreateButton, List, useForm, useModal, useTable } from '@refinedev/antd';
import { Button, Col, Divider, Form as AntdForm, Input, Modal, Typography } from 'antd';
import { useApiUrl, useCreate, useUpdate } from '@refinedev/core';
import { getToken } from 'authProvider';
import ContentHeader from 'components/contentHeader';
import { generateFilter } from 'dataProvider';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { Bank } from '@morgan-ustd/shared';
import queryString from 'query-string';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const BankList: FC = () => {
  const { t } = useTranslation('systemSettings');
  const apiUrl = useApiUrl();
  const [action, setAction] = useState<'create' | 'edit'>('edit');
  const [current, setCurrent] = useState<Bank>();
  const { form } = useForm();
  const { modalProps, show, close } = useModal();
  const { mutateAsync: update } = useUpdate();
  const { mutateAsync: create } = useCreate();

  const { tableProps, searchFormProps, filters } = useTable<Bank>({
    resource: 'banks',
    syncWithLocation: true,
  });

  const columnDeps: ColumnDependencies = { t, setCurrent, setAction, show };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('bank.title')}</title>
      </Helmet>
      <List
        title={<ContentHeader title={t('bank.title')} resource="feature-toggles" />}
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
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('bank.fields.bankName')} name="name">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
      <Modal {...modalProps} title={t('bank.actions.edit')} destroyOnClose onOk={form.submit}>
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
          <AntdForm.Item label={t('bank.fields.bankName')} name="name" rules={[{ required: true }]}>
            <Input />
          </AntdForm.Item>
        </AntdForm>
      </Modal>
    </>
  );
};

export default BankList;
