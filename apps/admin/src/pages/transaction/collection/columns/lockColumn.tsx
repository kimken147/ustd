import { Button, Popover, Space } from 'antd';
import { TextField } from '@refinedev/antd';
import { InfoCircleOutlined, LockOutlined, UnlockOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createLockColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, canEdit, profile, Modal } = deps;

  return {
    width: 80,
    fixed: 'left' as const,
    render(_value, record) {
      const { locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
      let className = '';
      if (canEdit) {
        className = `${
          locked
            ? notLocker
              ? `!bg-[#bebebe]`
              : '!bg-black'
            : '!bg-[#ffbe4d]'
        } !text-white border-0`;
      }
      return (
        <Space>
          <Button
            className={className}
            disabled={!canEdit || notLocker}
            icon={locked ? <LockOutlined /> : <UnlockOutlined />}
            onClick={() =>
              Modal.confirm({
                title: t('messages.confirmLock', {
                  action: record.locked
                    ? t('messages.unlock')
                    : t('messages.lock'),
                }),
                id: record.id,
                resource: 'transactions',
                values: {
                  locked: !record.locked,
                },
              })
            }
          />
          {locked && (
            <Popover
              trigger={'click'}
              content={
                <Space direction="vertical">
                  <TextField
                    value={t('info.lockedBy', {
                      name: record.locked_by?.name,
                    })}
                  />
                  <TextField
                    value={t('info.lockedAt', {
                      time: dayjs(record.locked_at).format('YYYY-MM-DD HH:mm:ss'),
                    })}
                  />
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#1677ff]" />
            </Popover>
          )}
        </Space>
      );
    },
  };
}
