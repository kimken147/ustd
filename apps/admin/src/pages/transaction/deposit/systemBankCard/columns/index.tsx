import { EditOutlined } from '@ant-design/icons';
import { DeleteButton, TextField } from '@refinedev/antd';
import { Button, Modal, Space } from 'antd';
import dayjs from 'dayjs';
import type { ColumnDependencies, SystemBankCardColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): SystemBankCardColumn[] {
  const { t, show, setSelectedKey, showUpdateUserModal, update } = deps;

  return [
    {
      title: t('fields.status'),
      dataIndex: 'status',
      render(value) {
        return (
          <TextField
            value={value ? t('status.onShelf') : t('status.offShelf')}
            className={value ? 'text-[#16A34A]' : 'text-[#FF4D4F]'}
          />
        );
      },
    },
    {
      title: t('fields.bankCardNumber'),
      dataIndex: 'bank_card_number',
      render(value) {
        return <TextField value={value} />;
      },
    },
    {
      title: t('fields.bankCardHolderName'),
      dataIndex: 'bank_card_holder_name',
      render(value) {
        return <TextField value={value} />;
      },
    },
    {
      title: t('fields.bankName'),
      dataIndex: 'bank_name',
      render(value) {
        return <TextField value={value} />;
      },
    },
    {
      title: t('fields.bankProvince'),
      dataIndex: 'bank_province',
      render(value) {
        return <TextField value={value} />;
      },
    },
    {
      title: t('fields.bankCity'),
      dataIndex: 'bank_city',
      render(value) {
        return <TextField value={value} />;
      },
    },
    {
      title: t('fields.quota'),
      dataIndex: 'balance',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  title: t('actions.editQuota'),
                  filterFormItems: ['balance'],
                  id: record.id,
                  initialValues: {
                    balance: record.balance,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.createdAt'),
      dataIndex: 'created_at',
      render(value) {
        return dayjs(value).format('YYYY-MM-DD HH:mm:ss');
      },
    },
    {
      title: t('fields.userFullOpen'),
      dataIndex: 'users',
      render(value, record) {
        return (
          <Space>
            <TextField
              value={value
                ?.map((item: { name: string; share_descendants: boolean }) => {
                  if (item.share_descendants) {
                    return `${item.name}(${t('info.fullLineOpen')})`;
                  }
                  return item.name;
                })
                .join(', ')}
            />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                setSelectedKey(record.id);
                showUpdateUserModal();
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.note'),
      dataIndex: 'note',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  title: t('actions.editNote'),
                  filterFormItems: ['note'],
                  id: record.id,
                  initialValues: {
                    note: record.note,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('actions.operation'),
      dataIndex: 'status',
      render(value, record) {
        return (
          <Space>
            <Button
              onClick={() => {
                if (value) {
                  Modal.confirm({
                    title: t('actions.confirmOffShelf'),
                    onOk: async () => {
                      await update({
                        resource: 'system-bank-cards',
                        id: record.id,
                        values: {
                          status: 0,
                          id: record.id,
                        },
                        successNotification: {
                          message: t('messages.offShelfSuccess'),
                          type: 'success',
                        },
                      });
                    },
                  });
                } else {
                  show({
                    title: value ? t('actions.offShelf') : t('actions.onShelf'),
                    filterFormItems: ['balance', 'status'],
                    initialValues: {
                      balance: record.balance,
                      status: value ? 0 : 1,
                    },
                    id: record.id,
                  });
                }
              }}
            >
              {value ? t('actions.offShelf') : t('actions.onShelf')}
            </Button>
            <DeleteButton>{t('actions.delete')}</DeleteButton>
          </Space>
        );
      },
    },
  ];
}
