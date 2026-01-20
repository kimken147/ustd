import { CreateButton, List, ListButton, useTable } from '@refinedev/antd';
import { Col, Divider, Input, InputNumber, Select } from 'antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import useProvider from 'hooks/useProvider';
import useUpdateModal from 'hooks/useUpdateModal';
import { Provider } from 'interfaces/provider';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns, UpdateProviderFormField, type ColumnDependencies } from './columns';

const ProviderList: FC = () => {
  const { Select: ProviderSelect } = useProvider();

  const { tableProps, searchFormProps } = useTable<Provider>({
    resource: 'providers',
    syncWithLocation: true,
  });

  const { show, Modal } = useUpdateModal<Provider>({
    transferFormValues: values => {
      if (values.type === 'minus') {
        if (values[UpdateProviderFormField.balance_delta]) {
          values[UpdateProviderFormField.balance_delta] = -values[UpdateProviderFormField.balance_delta];
        }
        if (values[UpdateProviderFormField.frozen_balance_delta]) {
          values[UpdateProviderFormField.frozen_balance_delta] =
            -values[UpdateProviderFormField.frozen_balance_delta];
        }
        if (values[UpdateProviderFormField.profit_delta]) {
          values[UpdateProviderFormField.profit_delta] = -values[UpdateProviderFormField.profit_delta];
        }
      }
      return values;
    },
    formItems: [
      { label: '名称', name: 'name', children: <Input /> },
      {
        label: '类型',
        name: 'type',
        children: (
          <Select
            options={['add', 'minus'].map(type => ({
              label: type === 'add' ? '增加' : '减少',
              value: type,
            }))}
          />
        ),
      },
      { label: '总余额', name: UpdateProviderFormField.balance_delta, children: <InputNumber className="w-full" /> },
      { label: '冻结余额', name: UpdateProviderFormField.frozen_balance_delta, children: <InputNumber className="w-full" /> },
      { label: '红利', name: UpdateProviderFormField.profit_delta, children: <InputNumber className="w-full" /> },
      { label: '备注', name: UpdateProviderFormField.note, children: <Input.TextArea /> },
      { label: '提现手续费', name: UpdateProviderFormField.withdraw_fee, children: <InputNumber /> },
    ],
  });

  const columnDeps: ColumnDependencies = { show, Modal };
  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>群组管理</title>
      </Helmet>
      <List
        headerButtons={() => (
          <>
            <ListButton resource="merchant-transaction-groups">代收专线</ListButton>
            <ListButton resource="merchant-matching-deposit-groups">代付专线</ListButton>
            <CreateButton>建立群组</CreateButton>
          </>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label="群组名称" name="provider_name_or_username[]">
                <ProviderSelect mode="multiple" allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
      <Modal
        defaultValue={{
          type: 'add',
          [UpdateProviderFormField.balance_delta]: 0,
          [UpdateProviderFormField.frozen_balance_delta]: 0,
          [UpdateProviderFormField.profit_delta]: 0,
        }}
      />
    </>
  );
};

export default ProviderList;
