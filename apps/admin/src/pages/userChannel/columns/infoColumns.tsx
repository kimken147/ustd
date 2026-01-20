import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Space } from 'antd';
import numeral from 'numeral';
import { Purple } from '@morgan-ustd/shared';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createNoteColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, showUpdateModal } = deps;

  return {
    dataIndex: 'note',
    title: t('fields.note'),
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <EditOutlined
            style={{ color: Purple }}
            onClick={() =>
              showUpdateModal({
                title: t('actions.editNote'),
                id: record.id,
                filterFormItems: ['note'],
                initialValues: { note: value },
              })
            }
          />
        </Space>
      );
    },
  };
}

export function createAccountNumberColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    dataIndex: 'id',
    title: t('fields.accountNumber'),
    render(value) {
      return numeral(value).format('00000');
    },
  };
}
