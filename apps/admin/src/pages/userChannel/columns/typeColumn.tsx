import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Popover, Space } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createTypeColumn(deps: ColumnDependencies): UserChannelColumn | null {
  const { t, region, canEdit, getChannelTypeText, mutateUserChannel } = deps;

  if (region === 'CN') {
    return null;
  }

  return {
    dataIndex: 'type',
    title: t('fields.type'),
    render(value, record) {
      const isOneTimeChild = record.is_one_time && record.address_type === 'child';

      return (
        <Space>
          <TextField value={getChannelTypeText(value)} />
          {canEdit && !isOneTimeChild ? (
            <Popover
              trigger="click"
              content={
                <ul className="popover-edit-list">
                  {[1, 2, 3]
                    .filter(x => x !== value)
                    .map(type => (
                      <li
                        key={type}
                        onClick={() =>
                          mutateUserChannel({
                            record,
                            values: { type },
                            title: t('confirmation.modifyType'),
                          })
                        }
                      >
                        {getChannelTypeText(type)}
                      </li>
                    ))}
                </ul>
              }
            >
              <Button icon={<EditOutlined className="text-[#6eb9ff]" />} />
            </Popover>
          ) : (
            <Button disabled icon={<EditOutlined />} />
          )}
        </Space>
      );
    },
  };
}
