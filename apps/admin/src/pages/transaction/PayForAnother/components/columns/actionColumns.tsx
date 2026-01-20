/**
 * Action columns for PayForAnother list
 * - Locked column
 * - Operation column
 * - Callback column
 * - Third party payout column
 */
import { Button, Popover, Space } from 'antd';
import { TextField } from '@refinedev/antd';
import {
  BranchesOutlined,
  CheckOutlined,
  CloseOutlined,
  DoubleRightOutlined,
  InfoCircleOutlined,
  LockOutlined,
  RedoOutlined,
  SelectOutlined,
  SettingOutlined,
  SwapRightOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import { TransactionType } from '@morgan-ustd/shared';
import dayjs from 'dayjs';
import type { ColumnContext, WithdrawColumn } from './types';

export function createLockedColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, canEdit, profile, modalConfirm, WithdrawStatus } = ctx;

  return {
    title: t('fields.locked'),
    dataIndex: 'locked',
    width: 80,
    render(value, record) {
      const { separated, locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
      let text = value ? t('status.unlocked') : t('status.locked');
      if (separated) text = t('withdraw.locked');
      const icon = value ? <LockOutlined /> : <UnlockOutlined />;
      const disabled =
        !canEdit ||
        separated ||
        notLocker ||
        record.status === WithdrawStatus.审核中 ||
        record.provider !== null;
      let className = '';
      if (canEdit && !separated) {
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
            disabled={disabled}
            danger={!value}
            icon={icon}
            onClick={() =>
              modalConfirm({
                title: t('messages.confirmLock', { action: text }),
                id: record.id,
                values: { locked: !value },
              })
            }
            className={`${disabled ? `!bg-black/4` : className}`}
          />
          {locked && (
            <Popover
              trigger={'click'}
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

export function createOperationColumn(ctx: ColumnContext): WithdrawColumn {
  const {
    t,
    canEdit,
    profile,
    navigate,
    showUpdateModal,
    modalConfirm,
    WithdrawStatus,
  } = ctx;

  return {
    title: t('actions.operation'),
    dataIndex: 'locked',
    width: 100,
    render(_, record) {
      const { locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;

      // Review mode
      if (record.status === WithdrawStatus.审核中) {
        return (
          <Popover
            trigger={'click'}
            content={
              <Space>
                <Button
                  icon={<CheckOutlined />}
                  className="!bg-[#16a34a] !text-slate-50 border-0"
                  onClick={() => {
                    showUpdateModal({
                      title: t('actions.reviewSuccess'),
                      filterFormItems: ['withdrawType'],
                      formValues: { status: 101, to_id: null },
                      id: record.id,
                    });
                  }}
                >
                  {t('actions.reviewSuccess')}
                </Button>
                <Button
                  icon={<CloseOutlined />}
                  className="!bg-[#ff4d4f] !text-white border-0"
                  onClick={() => {
                    showUpdateModal({
                      title: t('actions.reviewFail'),
                      filterFormItems: ['note'],
                      formValues: { status: 8 },
                      id: record.id,
                    });
                  }}
                >
                  {t('actions.reviewFail')}
                </Button>
              </Space>
            }
          >
            <Button icon={<SelectOutlined />} type="primary">
              {t('actions.review')}
            </Button>
          </Popover>
        );
      }

      // Normal operation mode
      const canOperate =
        locked &&
        canEdit &&
        !record.separated &&
        !notLocker &&
        record.provider === null &&
        ![WithdrawStatus.失败].includes(record.status);

      if (!canOperate) {
        return <Button disabled icon={<SettingOutlined />} />;
      }

      const isFinished =
        record.status === WithdrawStatus.失败 ||
        record.status === WithdrawStatus.成功 ||
        record.status === WithdrawStatus.手动成功;

      return (
        <Popover
          content={
            <Space>
              <Button
                icon={<CheckOutlined />}
                disabled={isFinished}
                className={isFinished ? '' : '!bg-[#16a34a] !text-slate-50 border-0'}
                onClick={() =>
                  modalConfirm({
                    title: t('messages.confirmModifyStatus'),
                    id: record.id,
                    values: { status: WithdrawStatus.手动成功 },
                  })
                }
              >
                {t('actions.changeToSuccess')}
              </Button>
              <Button
                icon={<CloseOutlined />}
                disabled={record.status === WithdrawStatus.失败}
                className={
                  record.status === WithdrawStatus.失败
                    ? ''
                    : '!bg-[#ff4d4f] !text-white border-0'
                }
                onClick={() =>
                  modalConfirm({
                    title: t('messages.confirmModifyStatus'),
                    id: record.id,
                    values: { status: WithdrawStatus.失败 },
                  })
                }
              >
                {t('actions.changeToFail')}
              </Button>
              <Button
                onClick={() => navigate(`/child-withdraws/show/${record.id}`)}
                disabled={!record.separatable}
                icon={<BranchesOutlined className="rotate-180" />}
              >
                {t('actions.splitOrder')}
              </Button>
              <Button
                type="primary"
                disabled={isFinished}
                onClick={() =>
                  showUpdateModal({
                    title: t('actions.convertToProviderPayout'),
                    filterFormItems: ['to_id'],
                    id: record.id,
                    initialValues: { to_id: null },
                  })
                }
                icon={<DoubleRightOutlined />}
              >
                {t('withdraw.providerPayout')}
              </Button>
            </Space>
          }
          trigger={'click'}
        >
          <Button icon={<SettingOutlined />} type="primary" />
        </Popover>
      );
    },
  };
}

export function createCallbackColumn(ctx: ColumnContext): WithdrawColumn {
  const { t, apiUrl, mutateAsync, WithdrawStatus } = ctx;

  return {
    title: t('actions.callback'),
    responsive: ['md', 'lg', 'xl', 'xxl'] as const,
    width: 60,
    render: (_, record) => {
      const { status, notify_url } = record;
      if (!notify_url) return null;

      return (
        <Button
          icon={<RedoOutlined />}
          disabled={
            status === WithdrawStatus.匹配中 || status === WithdrawStatus.等待付款
          }
          onClick={async () => {
            await mutateAsync({
              url: `${apiUrl}/transactions/${record.id}/renotify`,
              method: 'post',
              values: record,
              successNotification: {
                message: t('messages.callbackSuccess'),
                type: 'success',
              },
            });
          }}
        />
      );
    },
  };
}

export function createThirdPartyPayoutColumn(ctx: ColumnContext): WithdrawColumn {
  const {
    t,
    apiUrl,
    showUpdateModal,
    refetch,
    setSelectMerchantId,
    WithdrawStatus,
  } = ctx;

  return {
    title: t('actions.thirdPartyPayout'),
    responsive: ['lg', 'xl', 'xxl'] as const,
    width: 60,
    render(_, record) {
      const isDisabled =
        record.status === WithdrawStatus.失败 ||
        record.status === WithdrawStatus.成功 ||
        record.status === WithdrawStatus.手动成功 ||
        record.type === TransactionType.TYPE_PAUFEN_WITHDRAW ||
        !record.locked ||
        (record.status !== WithdrawStatus.等待付款 &&
          record.type !== TransactionType.TYPE_NORMAL_WITHDRAW);

      return (
        <Button
          icon={<SwapRightOutlined />}
          disabled={isDisabled}
          onClick={() => {
            setSelectMerchantId(record.user.id);
            showUpdateModal({
              title: t('messages.confirmThirdPartyPayout'),
              id: record.id,
              filterFormItems: ['to_thirdchannel_id'],
              customMutateConfig: {
                url: `${apiUrl}/withdraws/${record.id}`,
                method: 'put',
                values: { id: record.id },
              },
              onSuccess() {
                setSelectMerchantId(0);
                refetch();
              },
            });
          }}
        />
      );
    },
  };
}

/**
 * Get all action columns
 */
export function getActionColumns(ctx: ColumnContext): WithdrawColumn[] {
  return [
    createLockedColumn(ctx),
    createOperationColumn(ctx),
    createCallbackColumn(ctx),
    createThirdPartyPayoutColumn(ctx),
  ];
}
