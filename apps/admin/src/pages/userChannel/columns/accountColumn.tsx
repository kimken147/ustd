import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  EditOutlined,
  InfoCircleOutlined,
} from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Popover, Space } from 'antd';
import dayjs from 'dayjs';
import { AccountStatus, Green, Purple, Gray, Red, Yellow, SyncStatus } from '@morgan-ustd/shared';
import type { ColumnDependencies, UserChannelColumn } from './types';
import type { ReactNode } from 'react';

export function createAccountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, region, canEdit, showUpdateModal } = deps;

  return {
    title: t('fields.account'),
    render(_, record) {
      const {
        account_status,
        sync_status,
        mpin,
        sync_at,
        password_status,
        email_status,
        email,
      } = record.detail;

      let icon: ReactNode = null;
      if (account_status === 'pass') {
        icon = <CheckCircleOutlined style={{ color: Green }} />;
      } else if (account_status === 'fail') {
        icon = <CloseCircleOutlined style={{ color: Red }} />;
      } else if (account_status === 'unverified') {
        icon = <InfoCircleOutlined style={{ color: Yellow }} />;
      }

      const renderItem =
        region === 'CN' ? null : (
          <>
            {mpin}
            <Button
              disabled={!canEdit}
              icon={<EditOutlined />}
              onClick={() =>
                showUpdateModal({
                  title: t('actions.editMpin'),
                  filterFormItems: ['mpin'],
                  id: record.id,
                  initialValues: { mpin },
                })
              }
              style={{ color: canEdit ? Purple : Gray }}
            />
            <Popover content={<TextField value={AccountStatus[account_status]} />}>
              {icon}
            </Popover>
          </>
        );

      return (
        <>
          <div>
            <Space>
              {record.account}
              {renderItem}
            </Space>
          </div>
          {sync_at && (
            <Space>
              <TextField
                value={t('messages.syncTime', {
                  time: dayjs(sync_at).fromNow(),
                })}
              />
              <TextField value={SyncStatus[sync_status]} />
            </Space>
          )}
          <div>
            {password_status && (
              <Space>
                <TextField value={t('messages.passwordStatus')} />
                <TextField value={password_status} />
              </Space>
            )}
          </div>
          {email_status && (
            <div>
              <Space>
                <TextField value={t('messages.emailStatus')} />
                <TextField value={email_status} />
              </Space>
              <div>
                <Space>
                  <TextField value={`(${email ?? ''})`} />
                </Space>
              </div>
            </div>
          )}
        </>
      );
    },
  };
}
