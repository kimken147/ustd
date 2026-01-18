import { DeleteOutlined, EditOutlined } from '@ant-design/icons';
import {
  Button,
  Input,
  InputNumber,
  List,
  Space,
  Switch,
  TextField,
} from '@pankod/refine-antd';
import { useApiUrl, useCan, useList } from '@pankod/refine-core';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Channel, ChannelGroup } from 'interfaces/channel';
import numeral from 'numeral';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const ChannelList: FC = () => {
  const { t } = useTranslation('channel');
  const apiUrl = useApiUrl();
  const { data: canEdit } = useCan({
    action: '15',
    resource: 'channels',
  });
  const { Table, refetch } = useTable<Channel>({
    hasPagination: false,
  });
  const { show, Modal } = useUpdateModal({
    formItems: [
      {
        label: (
          <div>
            <TextField value={t('fields.amount')} />
            <TextField value={t('fields.amountPlaceholder')} />
          </div>
        ),
        name: 'amount',
        children: <Input />,
      },
      {
        label: t('fields.matchTimeout'),
        name: 'order_timeout',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.paymentTimeout'),
        name: 'transaction_timeout',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.floatingAmountRange'),
        name: 'floating',
        children: <InputNumber className="w-full" step={1} min={-9} max={9} />,
      },
    ],
    // transferFormValues(record) {
    //     record.floating = Math.floor(record.floating);
    //     return record;
    // },
    onSuccess: () => {
      refetch();
    },
  });

  return (
    <>
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <List title={t('title')}>
        <Table rowKey={'code'}>
          <Table.Column title={t('fields.channelName')} dataIndex={'name'} />
          <Table.Column title={t('fields.channelCode')} dataIndex={'code'} />
          <Table.Column<Channel>
            title={t('fields.amountLimit')}
            dataIndex={'channel_groups'}
            render={(value: Channel['channel_groups'], record) => {
              return (
                <Space direction="vertical">
                  {value.map((channelGroup: ChannelGroup) => (
                    <Space key={channelGroup.id}>
                      <TextField
                        value={channelGroup.amount_description}
                        className="bg-stone-200 py-1 px-2"
                      />
                      <Button
                        disabled={!canEdit?.can}
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
                                  if (index === 0)
                                    return `${numeral(cur).value()}`;
                                  return `${prev}~${numeral(cur).value()}`;
                                }, ''),
                            },
                          })
                        }
                      />
                      <Button
                        icon={
                          <DeleteOutlined
                            className={canEdit?.can ? 'text-[#ff4d4f]' : ''}
                          />
                        }
                        className={canEdit?.can ? '!border-[#d9d9d9]' : ''}
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
                        disabled={!canEdit?.can}
                      />
                    </Space>
                  ))}
                  <Button
                    disabled={!canEdit?.can}
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
            }}
          />
          <Table.Column<Channel>
            title={t('fields.status')}
            dataIndex={'status'}
            render={(value, record) => (
              <Switch
                disabled={!canEdit?.can}
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmModifyStatus'),
                    id: record.code,
                    values: {
                      status: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.matchExpirySwitch')}
            dataIndex={'order_timeout_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEdit?.can}
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmMatchExpirySwitch'),
                    id: record.code,
                    values: {
                      order_timeout_enable: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.matchTimeout')}
            dataIndex={'order_timeout'}
            render={(value, record) => (
              <Space>
                <TextField value={`${value}${t('fields.seconds')}`} />
                <Button
                  disabled={!canEdit?.can}
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  onClick={() =>
                    show({
                      title: t('actions.editMatchTimeout'),
                      id: record.code,
                      filterFormItems: ['order_timeout'],
                      initialValues: {
                        order_timeout: value,
                      },
                    })
                  }
                />
              </Space>
            )}
          />
          <Table.Column<Channel>
            title={t('fields.paymentExpirySwitch')}
            dataIndex={'transaction_timeout_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEdit?.can}
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmPaymentExpirySwitch'),
                    id: record.code,
                    values: {
                      transaction_timeout_enable: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.paymentTimeout')}
            dataIndex={'transaction_timeout'}
            render={(value, record) => (
              <Space>
                <TextField value={`${value}${t('fields.seconds')}`} />
                <Button
                  disabled={!canEdit?.can}
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  onClick={() =>
                    show({
                      title: t('actions.editPaymentTimeout'),
                      id: record.code,
                      filterFormItems: ['transaction_timeout'],
                      initialValues: {
                        transaction_timeout: value,
                      },
                    })
                  }
                />
              </Space>
            )}
          />
          <Table.Column<Channel>
            title={t('fields.realNameSwitch')}
            dataIndex={'real_name_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEdit?.can}
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmRealNameSwitch'),
                    id: record.code,
                    values: {
                      real_name_enable: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.noteSwitch')}
            dataIndex={'note_enable'}
            render={(value, record) => (
              <Switch
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmNoteSwitch'),
                    id: record.code,
                    values: {
                      note_enable: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.floatingAmountSwitch')}
            dataIndex={'floating_enable'}
            render={(value, record) => (
              <Switch
                checked={value}
                onChange={value =>
                  Modal.confirm({
                    title: t('messages.confirmFloatingSwitch'),
                    id: record.code,
                    values: {
                      floating_enable: value,
                    },
                  })
                }
              />
            )}
          />
          <Table.Column<Channel>
            title={t('fields.floatingAmountRange')}
            dataIndex={'floating'}
            render={(value, record) => (
              <Space>
                <TextField value={value} />
                <EditOutlined
                  onClick={() =>
                    show({
                      title: t('actions.editFloatingRange'),
                      id: record.code,
                      filterFormItems: ['floating'],
                      initialValues: {
                        floating: value,
                      },
                    })
                  }
                />
              </Space>
            )}
          />
        </Table>
      </List>
      <Modal />
    </>
  );
};

export default ChannelList;
