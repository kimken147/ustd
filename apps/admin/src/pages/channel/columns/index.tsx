import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space, Switch } from 'antd';
import type { Channel, ChannelGroup } from '@morgan-ustd/shared';
import numeral from 'numeral';
import type { ColumnDependencies, ChannelColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): ChannelColumn[] {
  const { t, canEdit, apiUrl, show, Modal, refetch } = deps;

  return [
    {
      title: t('fields.channelName'),
      dataIndex: 'name',
    },
    {
      title: t('fields.channelCode'),
      dataIndex: 'code',
    },
    {
      title: t('fields.amountLimit'),
      dataIndex: 'channel_groups',
      render(value: Channel['channel_groups'], record) {
        return (
          <Space direction="vertical">
            {value.map((channelGroup: ChannelGroup) => (
              <Space key={channelGroup.id}>
                <TextField
                  value={channelGroup.amount_description}
                  className="bg-stone-200 py-1 px-2"
                />
                <Button
                  disabled={!canEdit}
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  onClick={() =>
                    show({
                      title: t('actions.editAmount'),
                      id: channelGroup.id,
                      filterFormItems: ['amount'],
                      resource: 'channel-groups',
                      initialValues: {
                        amount: channelGroup.amount_description
                          .split('~')
                          .reduce((prev, cur, index) => {
                            if (index === 0) return `${numeral(cur).value()}`;
                            return `${prev}~${numeral(cur).value()}`;
                          }, ''),
                      },
                    })
                  }
                />
                <Button
                  icon={<DeleteOutlined className={canEdit ? 'text-[#ff4d4f]' : ''} />}
                  className={canEdit ? '!border-[#d9d9d9]' : ''}
                  onClick={() =>
                    Modal.confirm({
                      title: t('messages.confirmDeleteAmount'),
                      id: channelGroup.id,
                      customMutateConfig: {
                        url: `${apiUrl}/channel-groups/${channelGroup.id}`,
                        method: 'delete',
                      },
                      onSuccess() {
                        refetch();
                      },
                    })
                  }
                  danger
                  disabled={!canEdit}
                />
              </Space>
            ))}
            <Button
              disabled={!canEdit}
              type="dashed"
              block
              size="small"
              onClick={() =>
                show({
                  title: t('actions.addAmount'),
                  id: '',
                  filterFormItems: ['amount'],
                  resource: 'channel-groups',
                  mode: 'create',
                  formValues: {
                    channel_code: record.code,
                  },
                })
              }
            >
              {t('actions.addOne')}
            </Button>
          </Space>
        );
      },
    },
    {
      title: t('fields.status'),
      dataIndex: 'status',
      render(value, record) {
        return (
          <Switch
            disabled={!canEdit}
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmModifyStatus'),
                id: record.code,
                values: { status: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.matchExpirySwitch'),
      dataIndex: 'order_timeout_enable',
      render(value, record) {
        return (
          <Switch
            disabled={!canEdit}
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmMatchExpirySwitch'),
                id: record.code,
                values: { order_timeout_enable: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.matchTimeout'),
      dataIndex: 'order_timeout',
      render(value, record) {
        return (
          <Space>
            <TextField value={`${value}${t('fields.seconds')}`} />
            <Button
              disabled={!canEdit}
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() =>
                show({
                  title: t('actions.editMatchTimeout'),
                  id: record.code,
                  filterFormItems: ['order_timeout'],
                  initialValues: { order_timeout: value },
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.paymentExpirySwitch'),
      dataIndex: 'transaction_timeout_enable',
      render(value, record) {
        return (
          <Switch
            disabled={!canEdit}
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmPaymentExpirySwitch'),
                id: record.code,
                values: { transaction_timeout_enable: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.paymentTimeout'),
      dataIndex: 'transaction_timeout',
      render(value, record) {
        return (
          <Space>
            <TextField value={`${value}${t('fields.seconds')}`} />
            <Button
              disabled={!canEdit}
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() =>
                show({
                  title: t('actions.editPaymentTimeout'),
                  id: record.code,
                  filterFormItems: ['transaction_timeout'],
                  initialValues: { transaction_timeout: value },
                })
              }
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.realNameSwitch'),
      dataIndex: 'real_name_enable',
      render(value, record) {
        return (
          <Switch
            disabled={!canEdit}
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmRealNameSwitch'),
                id: record.code,
                values: { real_name_enable: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.noteSwitch'),
      dataIndex: 'note_enable',
      render(value, record) {
        return (
          <Switch
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmNoteSwitch'),
                id: record.code,
                values: { note_enable: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.floatingAmountSwitch'),
      dataIndex: 'floating_enable',
      render(value, record) {
        return (
          <Switch
            checked={value}
            onChange={checked =>
              Modal.confirm({
                title: t('messages.confirmFloatingSwitch'),
                id: record.code,
                values: { floating_enable: checked },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.floatingAmountRange'),
      dataIndex: 'floating',
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              onClick={() =>
                show({
                  title: t('actions.editFloatingRange'),
                  id: record.code,
                  filterFormItems: ['floating'],
                  initialValues: { floating: value },
                })
              }
            />
          </Space>
        );
      },
    },
  ];
}
