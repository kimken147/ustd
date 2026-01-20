import { Button, Popover, Space, Typography } from 'antd';
import { ShowButton, TextField } from '@refinedev/antd';
import { CopyOutlined, EditOutlined, InfoCircleOutlined } from '@ant-design/icons';
import { axiosInstance } from '@refinedev/simple-rest';
import dayjs from 'dayjs';
import type { TransactionNote } from '@morgan-ustd/shared';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createSystemOrderColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, apiUrl, canEdit, show } = deps;

  return {
    title: t('fields.systemOrderNumber'),
    dataIndex: 'system_order_number',
    width: 200,
    fixed: 'left' as const,
    render(value, record) {
      return (
        <Space>
          <Typography.Paragraph
            copyable={{
              text: value,
              icon: <CopyOutlined className="text-[#6eb9ff]" />,
            }}
            className="!mb-0"
          >
            <ShowButton recordItemId={record.id} icon={false}>
              <TextField value={value} delete={record.child_system_order_number} />
            </ShowButton>
          </Typography.Paragraph>
          {record.child_system_order_number ? (
            <Popover
              trigger={'click'}
              content={
                <Space>
                  <TextField value={`${t('fields.emptyOrderNumber')}: `} />
                  <TextField
                    value={
                      <ShowButton icon={null} recordItemId={record.id}>
                        {record.child_system_order_number}
                      </ShowButton>
                    }
                    copyable={{
                      text: record.child_system_order_number,
                      icon: <CopyOutlined className="text-[#6eb9ff]" />,
                    }}
                  />
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          ) : null}
          {record.parent_system_order_number ? (
            <Popover
              trigger={'click'}
              content={
                <Space>
                  <TextField value={`${t('fields.originalOrderNumber')}: `} />
                  <TextField
                    value={
                      <ShowButton icon={null} recordItemId={record.id}>
                        {record.parent_system_order_number}
                      </ShowButton>
                    }
                    copyable={{
                      text: record.parent_system_order_number,
                      icon: <CopyOutlined className="text-[#6eb9ff]" />,
                    }}
                  />
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          ) : null}
          <Button
            disabled={!canEdit}
            icon={<EditOutlined />}
            className={record.note_exist ? 'text-[#6eb9ff]' : 'text-gray-300'}
            onClick={async () => {
              const { data: notes } = await axiosInstance.get<IRes<TransactionNote[]>>(
                `${apiUrl}/transactions/${record.id}/transaction-notes`
              );
              show({
                id: record.id,
                filterFormItems: ['note', 'transaction_id'],
                title: t('actions.addNote'),
                initialValues: {
                  transaction_id: record.id,
                },
                children: (
                  <Space direction="vertical">
                    {notes?.data.map((note, index) => {
                      return (
                        <Space direction="vertical" key={index}>
                          <TextField value={note.note} code className="text-[#1677ff]" />
                          <TextField
                            value={`${
                              note.user
                                ? `${note.user.name}`
                                : t('info.note', {
                                    note: dayjs(note.created_at).format(
                                      'YYYY-MM-DD HH:mm:ss'
                                    ),
                                  })
                            }`}
                          />
                        </Space>
                      );
                    })}
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

export function createMerchantOrderColumn(deps: ColumnDependencies): CollectionColumn {
  const { t } = deps;

  return {
    title: t('fields.merchantOrderNumber'),
    dataIndex: 'order_number',
    responsive: ['md', 'lg', 'xl', 'xxl'],
    render(value) {
      return value ? (
        <Typography.Paragraph
          copyable={{
            text: value,
            icon: <CopyOutlined className="text-[#6eb9ff]" />,
          }}
          className="!mb-0"
        >
          {value}
        </Typography.Paragraph>
      ) : null;
    },
  };
}
