import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space, Switch } from 'antd';
import { ColumnDependencies, ProviderColumn, UpdateProviderFormField } from './types';

export type { ColumnDependencies } from './types';
export { UpdateProviderFormField } from './types';

export function useColumns(deps: ColumnDependencies): ProviderColumn[] {
  const { show, Modal } = deps;

  return [
    {
      title: '群组名称',
      dataIndex: 'name',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  title: '群组名称',
                  id: record.id,
                  filterFormItems: ['name'],
                  initialValues: { name: value },
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: '收款总开关',
      dataIndex: 'transaction_enable',
      render(value, record) {
        return (
          <Switch
            checked={value}
            onChange={checked => {
              Modal.confirm({
                title: '确定要修改收款总开关吗',
                id: record.id,
                values: {
                  [UpdateProviderFormField.id]: record.id,
                  [UpdateProviderFormField.transaction_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: '付款总开关',
      dataIndex: 'paufen_deposit_enable',
      render(value, record) {
        return (
          <Switch
            checked={value}
            onChange={checked => {
              Modal.confirm({
                title: '确定要修改付款总开关吗',
                id: record.id,
                values: {
                  [UpdateProviderFormField.id]: record.id,
                  [UpdateProviderFormField.paufen_deposit_enable]: !!checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: '操作',
      dataIndex: 'id',
      render(id) {
        return (
          <Button
            danger
            onClick={() =>
              Modal.confirm({
                title: '确定要删除群组吗？',
                id,
                mode: 'delete',
              })
            }
          >
            删除
          </Button>
        );
      },
    },
  ];
}
