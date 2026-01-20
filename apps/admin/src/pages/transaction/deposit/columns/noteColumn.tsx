import { EditOutlined, FileSearchOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { axiosInstance } from '@refinedev/simple-rest';
import { Button, Card, Descriptions, Divider, Modal, Space } from 'antd';
import dayjs from 'dayjs';
import { Format, TransactionNote } from '@morgan-ustd/shared';
import type { ColumnDependencies, DepositColumn } from './types';

export function createNoteColumn(deps: ColumnDependencies): DepositColumn {
  const { t, apiUrl, show, refetch } = deps;

  return {
    title: '',
    dataIndex: 'note',
    render(_, record) {
      return (
        <Space>
          <Button
            icon={
              <EditOutlined
                style={{
                  color: record.note_exist ? '#6eb9ff' : '#d1d5db',
                }}
              />
            }
            onClick={async () => {
              const { data: notes } = await axiosInstance.get<IRes<TransactionNote[]>>(
                `${apiUrl}/transactions/${record.id}/transaction-notes`
              );
              show({
                id: record.id,
                filterFormItems: ['note', 'transaction_id'],
                title: t('actions.addNote'),
                children: (
                  <Space direction="vertical">
                    {notes?.data.map((note, idx) => (
                      <Space direction="vertical" key={idx}>
                        <TextField value={note.note} code className="text-[#1677ff]" />
                        <TextField
                          value={
                            note.user
                              ? note.user.name
                              : t('info.systemNote', {
                                  time: dayjs(note.created_at).format('YYYY-MM-DD HH:mm:ss'),
                                })
                          }
                        />
                      </Space>
                    ))}
                  </Space>
                ),
                customMutateConfig: {
                  url: `${apiUrl}/transaction-notes`,
                  method: 'post',
                  values: { transaction_id: record.id },
                },
                onSuccess: () => refetch(),
              });
            }}
          />
          <Button
            style={{
              color: record.certificate_files?.length ? '#6eb9ff' : '#d1d5db',
            }}
            icon={<FileSearchOutlined />}
            disabled={!record.certificate_files.length}
            onClick={() => {
              Modal.info({
                maskClosable: true,
                title: t('actions.viewCertificate'),
                content: (
                  <>
                    <Descriptions column={1} bordered>
                      <Descriptions.Item label={t('info.certificateBank')}>
                        {record.from_channel_account.bank_name}
                      </Descriptions.Item>
                      <Descriptions.Item label={t('info.certificateCardNumber')}>
                        {record.from_channel_account.bank_card_number}
                      </Descriptions.Item>
                      <Descriptions.Item label={t('info.certificateHolderName')}>
                        {record.from_channel_account.bank_card_holder_name}
                      </Descriptions.Item>
                      <Descriptions.Item label={t('fields.amount')}>
                        {record.amount}
                      </Descriptions.Item>
                      <Descriptions.Item label={t('fields.createdAt')}>
                        {dayjs(record.created_at).format(Format)}
                      </Descriptions.Item>
                    </Descriptions>
                    <Divider />
                    {record.certificate_files?.map(file => (
                      <Card key={file.id} className="mt-4">
                        <img src={file.url} alt="" />
                      </Card>
                    ))}
                  </>
                ),
              });
            }}
          />
        </Space>
      );
    },
  };
}
