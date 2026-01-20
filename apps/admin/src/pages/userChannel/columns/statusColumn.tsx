import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Popover, Space } from 'antd';
import Badge from 'components/badge';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createStatusColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, getChannelStatusText, mutateUserChannel } = deps;

  return {
    dataIndex: 'status',
    title: (
      <Space>
        <TextField value={t('fields.status')} />
      </Space>
    ),
    render(value, record) {
      let color = '#16a34a';
      if (value === 0) {
        color = '#bebebe';
      } else if (value === 1) {
        color = '#ff4d4f';
      }

      return (
        <Space>
          <Badge color={color} text={getChannelStatusText(value)} />
          {canEdit ? (
            <Popover
              trigger="click"
              content={
                <ul className="popover-edit-list">
                  {[0, 1, 2]
                    .filter(status => status !== value)
                    .map(status => (
                      <li
                        key={status}
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              status,
                              id: record.id,
                            },
                            title: t('confirmation.changeStatus'),
                          });
                        }}
                      >
                        {getChannelStatusText(status)}
                      </li>
                    ))}
                </ul>
              }
            >
              <Button icon={<EditOutlined className="text-[#6eb9ff]" />} />
            </Popover>
          ) : (
            <Button icon={<EditOutlined />} disabled />
          )}
        </Space>
      );
    },
  };
}
