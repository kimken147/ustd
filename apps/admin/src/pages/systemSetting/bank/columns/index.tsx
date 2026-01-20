import { DeleteButton, EditButton } from '@refinedev/antd';
import { Space } from 'antd';
import type { ColumnDependencies, BankColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): BankColumn[] {
  const { t, setCurrent, setAction, show } = deps;

  return [
    {
      title: t('bank.fields.bankName'),
      dataIndex: 'name',
    },
    {
      title: t('bank.actions.edit'),
      render(_, record) {
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
}
