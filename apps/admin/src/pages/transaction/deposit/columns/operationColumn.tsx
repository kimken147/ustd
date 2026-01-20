import {
  CheckOutlined,
  CloseOutlined,
  SettingOutlined,
  StepForwardOutlined,
} from '@ant-design/icons';
import { Button, Popover, Space } from 'antd';
import type { ColumnDependencies, DepositColumn } from './types';

export function createOperationColumn(deps: ColumnDependencies): DepositColumn {
  const { t, profile, Status, show, showSuccessModal, setCurrent, UpdateModal } = deps;

  return {
    title: t('actions.operation'),
    render(_, record) {
      const { locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;

      if (!locked || notLocker) {
        return <Button disabled icon={<SettingOutlined />} />;
      }

      return (
        <Popover
          content={
            <Space>
              <Button
                icon={<CheckOutlined />}
                disabled={
                  record.status === Status.失败 ||
                  record.status === Status.成功 ||
                  record.status === Status.手动成功
                }
                className={
                  record.status === Status.失败 ||
                  record.status === Status.成功 ||
                  record.status === Status.手动成功
                    ? ''
                    : '!bg-[#16a34a] !text-slate-50 border-0'
                }
                onClick={() => {
                  setCurrent(record);
                  showSuccessModal();
                }}
              >
                {t('actions.changeToSuccess')}
              </Button>
              <Button
                icon={<CloseOutlined />}
                disabled={record.status === Status.失败}
                className={
                  record.status === Status.失败
                    ? ''
                    : '!bg-[#ff4d4f] !text-white border-0'
                }
                onClick={() =>
                  UpdateModal.confirm({
                    title: t('messages.confirmModifyStatus'),
                    resource: 'deposits',
                    id: record.id,
                    values: { status: Status.失败 },
                  })
                }
              >
                {t('actions.changeToFail')}
              </Button>
              <Button
                icon={<StepForwardOutlined />}
                disabled={record.type === 3}
                onClick={() =>
                  show({
                    title: t('actions.systemPayout'),
                    filterFormItems: ['note'],
                    id: record.id,
                    formValues: { isEdit: false, to_id: 0 },
                  })
                }
              >
                {t('actions.systemPayout')}
              </Button>
            </Space>
          }
          trigger="click"
        >
          <Button icon={<SettingOutlined />} type="primary" />
        </Popover>
      );
    },
  };
}
