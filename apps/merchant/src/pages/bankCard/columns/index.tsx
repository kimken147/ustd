import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import { Badge, Space } from 'antd';
import type { BadgeProps } from 'antd';
import { DateField, DeleteButton, EditButton, TextField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, BankCardColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): BankCardColumn[] {
  const { t, showUpdateModal } = deps;

  return [
    {
      title: t('bankCard.fields.bankAccount'),
      dataIndex: 'bank_card_number',
    },
    {
      title: t('bankCard.fields.accountOwner'),
      dataIndex: 'bank_card_holder_name',
    },
    {
      title: t('bankCard.fields.bankName'),
      dataIndex: 'bank_name',
    },
    {
      title: t('status'),
      dataIndex: 'status',
      render(value) {
        let text = '';
        let status: BadgeProps['status'] = 'default';
        switch (value) {
          case 1:
            text = t('bankCard.review.wait');
            break;
          case 2:
            text = t('bankCard.review.success');
            status = 'success';
            break;
          case 3:
            text = t('bankCard.review.fail');
            status = 'error';
            break;
        }
        return (
          <Space>
            <Badge status={status} />
            <TextField value={text} />
          </Space>
        );
      },
    },
    {
      title: t('createAt'),
      dataIndex: 'created_at',
      render: value => <DateField value={value} format={Format} />,
    },
    {
      render(_, record) {
        return (
          <Space>
            <EditButton
              onClick={() => {
                showUpdateModal({
                  initialValues: record,
                  id: record.id,
                  filterFormItems: [],
                  title: t('bankCard.fields.edit'),
                });
              }}
              icon={<EditOutlined />}
            >
              {t('edit')}
            </EditButton>
            <DeleteButton
              recordItemId={record.id}
              icon={<DeleteOutlined />}
              danger
              successNotification={{ type: 'success', message: t('success') }}
            >
              {t('delete')}
            </DeleteButton>
          </Space>
        );
      },
    },
  ];
}
