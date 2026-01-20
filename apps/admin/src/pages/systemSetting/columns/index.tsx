import { EditOutlined } from '@ant-design/icons';
import { DateField, TextField } from '@refinedev/antd';
import { Space, Switch } from 'antd';
import type { ColumnDependencies, SystemSettingColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): SystemSettingColumn[] {
  const { t, show, Modal } = deps;

  return [
    {
      title: t('systemSetting.fields.name'),
      dataIndex: 'label',
      width: 300,
    },
    {
      title: t('systemSetting.fields.note'),
      dataIndex: 'note',
      width: 450,
      render: value => <TextField value={value} italic />,
    },
    {
      title: t('systemSetting.fields.switch'),
      dataIndex: 'enabled',
      render: (value, record) => (
        <Switch
          checked={value}
          onChange={checked =>
            Modal.confirm({
              title: t('systemSetting.messages.confirmModify'),
              id: record.id,
              values: { enabled: checked },
            })
          }
        />
      ),
      width: 150,
    },
    {
      title: t('systemSetting.fields.settingValue'),
      dataIndex: 'value',
      render: (value, record) => {
        if (value === null) return null;
        if (record.type === 'boolean' && record.id !== 34) return null;
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() =>
                show({
                  id: record.id,
                  initialValues: { value: record.value },
                  filterFormItems: ['value'],
                  title: t('systemSetting.actions.editSettingValue'),
                })
              }
            />
          </Space>
        );
      },
      width: 300,
    },
    {
      title: t('systemSetting.fields.unit'),
      dataIndex: 'unit',
    },
    {
      title: t('systemSetting.fields.updateTime'),
      dataIndex: 'updated_at',
      render: value => <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />,
    },
  ];
}
