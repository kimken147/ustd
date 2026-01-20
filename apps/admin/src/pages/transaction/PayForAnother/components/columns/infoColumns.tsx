/**
 * Information columns for PayForAnother list
 * - Order number
 * - Payment type
 * - Payer info
 * - User name
 */
import { Typography, Space, Button, Popover } from 'antd';
import { List as AntdList } from 'antd';
import { ShowButton, TextField } from '@refinedev/antd';
import {
  CopyOutlined,
  EditOutlined,
  InfoCircleOutlined,
} from '@ant-design/icons';
import {
  Withdraw,
  WithdrawUser as User,
  TransactionSubType,
  TransactionNote,
} from '@morgan-ustd/shared';
import numeral from 'numeral';
import dayjs from 'dayjs';
import type { ColumnContext, WithdrawColumn } from './types';

export function createOrderNumberColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, canEdit, apiUrl, showUpdateModal, axiosInstance } = ctx;

  return {
    title: t('fields.merchantOrderNumber'),
    dataIndex: 'order_number',
    render(value, record) {
      if (!value) return null;

      return (
        <Space>
          <Typography.Paragraph className="!mb-0">
            <ShowButton recordItemId={record.id} icon={null}>
              <TextField value={value} delete={record.separated} />
            </ShowButton>
            <TextField
              value={' '}
              copyable={{
                text: value,
                icon: <CopyOutlined className="text-[#6eb9ff]" />,
              }}
            />
          </Typography.Paragraph>

          {/* Child withdraws popover */}
          {record?.children?.length ? (
            <Popover
              trigger={'click'}
              content={
                <AntdList<Withdraw>
                  bordered
                  dataSource={record.children}
                  renderItem={(item, index) => (
                    <AntdList.Item key={item.id}>
                      <Space>
                        <TextField
                          value={t('childWithdraw.item', { number: index + 1 })}
                        />
                        <TextField
                          value={
                            <ShowButton icon={null} recordItemId={item.id}>
                              {item.order_number}
                            </ShowButton>
                          }
                        />
                        <TextField
                          value={''}
                          copyable={{ text: item.order_number }}
                        />
                      </Space>
                    </AntdList.Item>
                  )}
                />
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          ) : null}

          {/* Note edit button */}
          <Button
            disabled={!canEdit}
            icon={<EditOutlined />}
            className={record.note_exist ? 'text-[#6eb9ff]' : 'text-gray-300'}
            onClick={async () => {
              const { data } = await axiosInstance.get<IRes<TransactionNote[]>>(
                `${apiUrl}/transactions/${record.id}/transaction-notes`
              );

              const notes: Partial<TransactionNote>[] = [];
              if (record.note) {
                notes.push({
                  id: Date.now() as unknown as number,
                  note: record.note,
                  created_at: record.created_at,
                });
              }
              notes.push(...data.data);

              showUpdateModal({
                id: record.id,
                filterFormItems: ['note', 'transaction_id'],
                title: t('fields.note'),
                initialValues: { transaction_id: record.id },
                children: (
                  <Space direction="vertical">
                    {notes?.map((note, idx) => (
                      <Space direction="vertical" key={idx}>
                        <TextField
                          value={note.note}
                          code
                          className="text-[#1677ff]"
                        />
                        <TextField
                          value={`${
                            note.user
                              ? `${note.user.name}`
                              : t('info.systemNote', {
                                  time: dayjs(note.created_at).format(
                                    'YYYY-MM-DD HH:mm:ss'
                                  ),
                                })
                          }`}
                        />
                      </Space>
                    ))}
                  </Space>
                ),
                customMutateConfig: {
                  url: `${apiUrl}/transaction-notes`,
                  method: 'post',
                },
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createPaymentTypeColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.paymentType'),
    dataIndex: 'sub_type',
    render(value) {
      if (value === TransactionSubType.SUB_TYPE_WITHDRAW) return '下发';
      if (value === TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW)
        return t('types.agency');
      if (value === TransactionSubType.SUB_TYPE_WITHDRAW_PROFIT)
        return t('types.bonusWithdraw');
      return '';
    },
  };
}

export function createPayerInfoColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.payerInfo'),
    render(_, record) {
      if (record.to_channel_account) {
        const payer = `${record.to_channel_account.channel_code} - ${record.to_channel_account.account}`;
        return (
          <Space>
            <TextField value={payer} />
            <Popover
              trigger={'click'}
              content={
                <Space direction="vertical">
                  <TextField
                    value={t('info.accountNumber', {
                      number: numeral(record.to_channel_account.id).format('00000'),
                    })}
                  />
                  {record.to_channel_account?.note && (
                    <TextField
                      value={t('info.note', {
                        note: record.to_channel_account?.note,
                      })}
                    />
                  )}
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          </Space>
        );
      }

      if (record.provider) {
        return `${record.provider.name} (${record.provider.username})`;
      }

      if (record.thirdchannel) {
        return `${record.thirdchannel.name}(${record.thirdchannel.merchant_id})`;
      }

      return null;
    },
  };
}

export function createUserNameColumn(ctx: ColumnContext): WithdrawColumn {
  const { t } = ctx;

  return {
    title: t('fields.userName'),
    dataIndex: 'user',
    render(value: User) {
      const resource = value.role === 3 ? 'merchants' : 'providers';
      return (
        <Space>
          <div className="w-5 h-5 relative">
            <img
              src={value.role !== 3 ? '/provider-icon.png' : '/merchant-icon.png'}
              alt=""
              className="object-contain"
            />
          </div>
          <ShowButton icon={null} recordItemId={value?.id} resource={resource}>
            {value?.name}
          </ShowButton>
        </Space>
      );
    },
  };
}

/**
 * Get all info columns
 */
export function getInfoColumns(ctx: ColumnContext): WithdrawColumn[] {
  return [
    createOrderNumberColumn(ctx),
    createPaymentTypeColumn(ctx),
    createPayerInfoColumn(ctx),
    createUserNameColumn(ctx),
  ];
}
