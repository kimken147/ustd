import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import { DateField, ShowButton } from '@refinedev/antd';
import { Button, Popover, Space } from 'antd';
import Badge from 'components/badge';
import type { User } from 'interfaces/userBankCard';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, UserBankCardColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): UserBankCardColumn[] {
  const { t, Modal, canDelete, getStatusText } = deps;

  return [
    {
      title: t('bankCard.fields.userName'),
      dataIndex: 'user',
      render(value: User) {
        return (
          <Space>
            <div className="w-5 h-5 relative">
              <img
                src={value.role === 3 ? '/merchant-icon.png' : '/provider-icon.png'}
                alt=""
                className="object-contain"
              />
            </div>
            <ShowButton recordItemId={value.id} resource="merchants" icon={null}>
              {value.name}
            </ShowButton>
          </Space>
        );
      },
    },
    {
      title: t('bankCard.fields.cardNumber'),
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
      title: t('bankCard.fields.status'),
      dataIndex: 'status',
      render(value: number, record) {
        let text = '';
        let color = '';
        switch (value) {
          case 1:
            text = t('bankCard.review.wait');
            color = '#bebebe';
            break;
          case 2:
            text = t('bankCard.review.success');
            color = '#16a34a';
            break;
          case 3:
            text = t('bankCard.review.fail');
            color = '#ff4d4f';
            break;
        }
        return (
          <Space>
            <Badge text={text} color={color} />
            <Popover
              trigger="click"
              content={
                <ul className="popover-edit-list">
                  {[1, 2, 3]
                    .filter(x => x !== value)
                    .map(status => (
                      <li
                        key={status}
                        onClick={() => {
                          Modal.confirm({
                            id: record.id,
                            values: { status },
                            title: t('bankCard.confirmChangeStatus'),
                            className: 'z-10',
                          });
                        }}
                      >
                        {getStatusText(status)}
                      </li>
                    ))}
                </ul>
              }
            >
              <EditOutlined className="text-[#6eb9ff]" />
            </Popover>
          </Space>
        );
      },
    },
    {
      title: t('createAt'),
      dataIndex: 'created_at',
      render: (value: string) => <DateField value={value} format={Format} />,
    },
    {
      title: t('operation'),
      render(_, record) {
        return (
          <Space>
            <Button
              disabled={!canDelete}
              icon={<DeleteOutlined />}
              danger
              type="primary"
              onClick={() =>
                Modal.confirm({
                  title: t('bankCard.confirmDelete', { cardNumber: record.bank_card_number }),
                  id: record.id,
                  mode: 'delete',
                })
              }
            >
              {t('delete')}
            </Button>
          </Space>
        );
      },
    },
  ];
}
