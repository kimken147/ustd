import { InfoCircleOutlined, LockOutlined, UnlockOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Popover, Space } from 'antd';
import dayjs from 'dayjs';
import type { ColumnDependencies, DepositColumn } from './types';

export function createLockColumn(deps: ColumnDependencies): DepositColumn {
  const { t, profile, UpdateModal } = deps;

  return {
    title: t('fields.locked'),
    dataIndex: 'locked',
    render(value, record) {
      const { locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
      const text = value ? t('status.unlocked') : t('status.locked');
      const icon = value ? <LockOutlined /> : <UnlockOutlined />;
      const className = `${
        locked ? (notLocker ? '!bg-[#bebebe]' : '!bg-black') : '!bg-[#ffbe4d]'
      } !text-white border-0`;
      const danger = !value;

      const onClick = () =>
        UpdateModal.confirm({
          title: t('messages.confirmLock', { action: text }),
          resource: 'deposits',
          id: record.id,
          values: { locked: !value },
        });

      return (
        <Space>
          <Button
            danger={danger}
            icon={icon}
            onClick={onClick}
            disabled={notLocker}
            className={className}
          />
          {locked && (
            <Popover
              trigger="click"
              content={
                <Space direction="vertical">
                  <TextField
                    value={t('info.lockedBy', { name: record.locked_by?.name })}
                  />
                  <TextField
                    value={t('info.lockedAt', {
                      time: dayjs(record.locked_at).format('YYYY-MM-DD HH:mm:ss'),
                    })}
                  />
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          )}
        </Space>
      );
    },
  };
}
