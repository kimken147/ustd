import { Button, Card, Popover, Space, Modal as AntdModal } from 'antd';
import {
  CheckOutlined,
  CloseCircleOutlined,
  CloseOutlined,
  FileSearchOutlined,
  PlusOutlined,
  SettingOutlined,
} from '@ant-design/icons';
import type { CollectionColumn, ColumnDependencies } from './types';

export function createOperationColumn(deps: ColumnDependencies): CollectionColumn {
  const { t, apiUrl, canEdit, profile, tranStatus, refetch, show, Modal } = deps;

  return {
    title: t('actions.operation'),
    dataIndex: 'locked',
    width: 80,
    fixed: 'left' as const,
    render(value, record) {
      const { status } = record;
      const { locked, locked_by } = record;
      const notLocker =
        locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
      const disabled =
        !locked || [tranStatus.匹配超时].includes(status) || notLocker;

      return !disabled && canEdit ? (
        <Popover
          trigger={'click'}
          content={
            <Space>
              <Button
                className={
                  status === tranStatus.失败 ||
                  status === tranStatus.成功 ||
                  status === tranStatus.手动成功
                    ? ''
                    : '!bg-[#16a34a] !text-white border-0'
                }
                disabled={
                  status === tranStatus.失败 ||
                  status === tranStatus.成功 ||
                  status === tranStatus.手动成功
                }
                icon={<CheckOutlined />}
                onClick={() =>
                  Modal.confirm({
                    title: t('messages.confirmSupplement'),
                    id: record.id,
                    resource: 'transactions',
                    values: {
                      status: tranStatus.手动成功,
                    },
                  })
                }
              >
                {t('actions.supplement')}
              </Button>
              <Button
                className={`${
                  status === tranStatus.付款超时 || status === tranStatus.失败
                    ? ''
                    : '!bg-[#ff4d4f] !text-white border-0'
                }`}
                icon={<CloseOutlined />}
                disabled={
                  status === tranStatus.付款超时 || status === tranStatus.失败
                }
                onClick={() =>
                  Modal.confirm({
                    title: t('messages.confirmChangeToFail'),
                    id: record.id,
                    resource: 'transactions',
                    values: {
                      status: tranStatus.失败,
                    },
                  })
                }
              >
                {t('actions.changeToFail')}
              </Button>
              <Button
                disabled={[
                  tranStatus.成功,
                  tranStatus.手动成功,
                  tranStatus.失败,
                ].includes(status)}
                icon={<PlusOutlined />}
                onClick={() =>
                  show({
                    title: t('messages.confirmCreateEmptyOrder'),
                    id: record.id,
                    customMutateConfig: {
                      url: `${apiUrl}/transactions/${record.id}/child-transactions`,
                      method: 'post',
                      values: {
                        id: record.id,
                      },
                    },
                    filterFormItems: ['amount'],
                    successMessage: t('messages.createEmptyOrderSuccess'),
                    onSuccess() {
                      refetch();
                    },
                  })
                }
              >
                {t('buttons.addEmptyOrder')}
              </Button>
              <Button
                icon={<CloseCircleOutlined />}
                disabled={
                  record.refunded_at || record.status !== tranStatus.付款超时
                }
                onClick={() =>
                  show({
                    title: '销单',
                    filterFormItems: ['delay_settle_minutes'],
                    id: record.id,
                    initialValues: {
                      delay_settle_minutes: 0,
                    },
                    formValues: {
                      refund: 1,
                    },
                  })
                }
              >
                {t('actions.cancelOrder')}
              </Button>
              <Button
                style={{
                  color: record.certificate_files?.length ? '#6eb9ff' : '#d1d5db',
                }}
                icon={record.certificate_files.length ? <FileSearchOutlined /> : null}
                disabled={!record.certificate_files?.length}
                onClick={() =>
                  AntdModal.info({
                    title: t('actions.viewTransactionDetails'),
                    content: (
                      <>
                        {record.certificate_files?.map(file => (
                          <Card key={file.id} className="mt-4">
                            <img src={file.url} alt="" />
                          </Card>
                        ))}
                      </>
                    ),
                    maskClosable: true,
                  })
                }
              >
                {t('actions.viewTransactionDetails')}
              </Button>
            </Space>
          }
        >
          <Button icon={<SettingOutlined />} className="border-0" type="primary" />
        </Popover>
      ) : (
        <Button icon={<SettingOutlined />} disabled />
      );
    },
  };
}
