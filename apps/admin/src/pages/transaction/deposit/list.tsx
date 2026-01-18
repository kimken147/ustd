import {
  CheckOutlined,
  CloseOutlined,
  EditOutlined,
  FileSearchOutlined,
  InfoCircleOutlined,
  LockOutlined,
  SettingOutlined,
  StepForwardOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import {
  Button,
  Card,
  DatePicker,
  Descriptions,
  Divider,
  Input,
  List,
  ListButton,
  Modal,
  Popover,
  Select,
  ShowButton,
  Space,
  Table,
  TextField,
  useModal,
  Form as AntdForm,
  Checkbox,
  useForm,
} from '@pankod/refine-antd';
import {
  useApiUrl,
  useGetIdentity,
  useList,
  useUpdate,
} from '@pankod/refine-core';
import { axiosInstance } from '@pankod/refine-simple-rest';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import useTransactionStatus from 'hooks/useTransactionStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import { Deposit } from 'interfaces/deposit';
import { Merchant, TransactionNote, Format } from '@morgan-ustd/shared';
import { Provider } from 'interfaces/provider';
import { SystemSetting } from 'interfaces/systemSetting';
import numeral from 'numeral';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const DepositList: FC = () => {
  const { t } = useTranslation('transaction');
  const title = t('titles.providerDeposit');
  const apiUrl = useApiUrl();
  const defaultStartAt = dayjs().startOf('days').format();
  const { data: profile } = useGetIdentity<Profile>();
  const [current, setCurrent] = useState<Deposit>();
  const {
    getStatusText,
    Status,
    selectProps: transactionStatusSelectProps,
  } = useTransactionStatus();
  const { Select: ProviderSelect } = useSelector<Provider>({
    resource: 'providers',
    valueField: 'name',
  });
  const { Select: MerchantSelect } = useSelector<Merchant>({
    resource: 'merchants',
    valueField: 'name',
    labelRender(record) {
      return `${record.username}(${record.name})`;
    },
  });
  const { data: systemSettings } = useList<SystemSetting>({
    resource: 'feature-toggles',
    config: {
      hasPagination: false,
    },
  });
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();
  const { form, Form, tableProps, tableOutterStyle, refetch } =
    useTable<Deposit>({
      resource: 'deposits',
      formItems: [
        {
          label: t('fields.startDate'),
          name: 'started_at',
          trigger: 'onSelect',
          children: (
            <CustomDatePicker
              className="w-full"
              onFastSelectorChange={(startAt, endAt) =>
                form.setFieldsValue({
                  started_at: startAt,
                  ended_at: endAt,
                })
              }
            />
          ),
        },
        {
          label: t('fields.endDate'),
          name: 'ended_at',
          children: (
            <DatePicker
              className="w-full"
              disabledDate={current => {
                const startAt = form.getFieldValue('started_at') as Dayjs;
                return (
                  current &&
                  (current > startAt.add(1, 'month') || current < startAt)
                );
              }}
            />
          ),
        },
        {
          label: t('fields.merchantOrderOrSystemOrder'),
          name: 'order_number_or_system_order_number',
          children: <Input />,
        },
        {
          label: t('fields.orderStatus'),
          name: 'status[]',
          children: (
            <Select {...transactionStatusSelectProps} mode="multiple" />
          ),
        },
        {
          label: t('fields.lockedBy'),
          name: 'operator_name_or_username',
          children: <Input />,
          collapse: true,
        },
        {
          label: t('fields.providerDepositType'),
          name: 'type',
          children: (
            <Select
              options={[
                {
                  label: t('types.all'),
                  value: null,
                },
                {
                  label: t('types.paufenDeposit'),
                  value: 2,
                },
                {
                  label: t('types.generalDeposit'),
                  value: 3,
                },
              ]}
            />
          ),
          collapse: true,
        },
        {
          label: t('filters.providerNameOrAccount'),
          name: 'provider_name_or_username[]',
          children: <ProviderSelect />,
          collapse: true,
        },
        {
          label: t('fields.merchantNameOrAccount'),
          name: 'merchant_name_or_username',
          children: <MerchantSelect />,
          collapse: true,
        },
        {
          label: t('fields.bankCardKeyword'),
          name: 'bank_card_q',
          children: <Input />,
          collapse: true,
        },
        {
          label: t('fields.orderAmount'),
          name: 'amount',
          children: <Input placeholder={t('fields.amountRange')} />,
          collapse: true,
        },
      ],
      columns: [
        {
          title: '',
          dataIndex: 'note',
          render(value, record, index) {
            return (
              <Space>
                {/* <TextField value={value} /> */}
                <Button
                  icon={
                    <EditOutlined
                      style={{
                        color: record.note_exist ? '#6eb9ff' : '#d1d5db',
                      }}
                    />
                  }
                  onClick={async () => {
                    const { data: notes } = await axiosInstance.get<
                      IRes<TransactionNote[]>
                    >(`${apiUrl}/transactions/${record.id}/transaction-notes`);
                    show({
                      id: record.id,
                      filterFormItems: ['note', 'transaction_id'],
                      title: t('actions.addNote'),
                      children: (
                        <Space direction="vertical">
                          {notes?.data.map(note => {
                            return (
                              <Space direction="vertical">
                                <TextField
                                  value={note.note}
                                  code
                                  className="text-[#1677ff]"
                                />
                                <TextField
                                  value={`${
                                    note.user
                                      ? `${note.user.name}`
                                      : t('info.systemNote', {
                                          time: dayjs(note.created_at).format(
                                            'YYYY-MM-DD HH:mm:ss'
                                          ),
                                        })
                                  }`}
                                />
                              </Space>
                            );
                          })}
                        </Space>
                      ),
                      customMutateConfig: {
                        url: `${apiUrl}/transaction-notes`,
                        method: 'post',
                        values: {
                          transaction_id: record.id,
                        },
                      },
                      onSuccess: () => {
                        refetch();
                      },
                    });
                  }}
                />
                <Button
                  style={{
                    color: record.certificate_files?.length
                      ? '#6eb9ff'
                      : '#d1d5db',
                  }}
                  icon={<FileSearchOutlined />}
                  disabled={!record.certificate_files.length}
                  onClick={() => {
                    Modal.info({
                      maskClosable: true,
                      title: t('actions.viewCertificate'),
                      content: (
                        <>
                          <Descriptions column={1} bordered>
                            <Descriptions.Item
                              label={t('info.certificateBank')}
                            >
                              {record.from_channel_account.bank_name}
                            </Descriptions.Item>
                            <Descriptions.Item
                              label={t('info.certificateCardNumber')}
                            >
                              {record.from_channel_account.bank_card_number}
                            </Descriptions.Item>
                            <Descriptions.Item
                              label={t('info.certificateHolderName')}
                            >
                              {
                                record.from_channel_account
                                  .bank_card_holder_name
                              }
                            </Descriptions.Item>
                            <Descriptions.Item label={t('fields.amount')}>
                              {record.amount}
                            </Descriptions.Item>
                            <Descriptions.Item label={t('fields.createdAt')}>
                              {dayjs(record.created_at).format(Format)}
                            </Descriptions.Item>
                          </Descriptions>
                          <Divider />
                          {/*{record.locked && (*/}
                          {/*    <div className="flex gap-2">*/}
                          {/*        <Button*/}
                          {/*            style={{*/}
                          {/*                flexGrow: 1,*/}
                          {/*            }}*/}
                          {/*            icon={<CheckOutlined />}*/}
                          {/*            disabled={*/}
                          {/*                record.status === depositRecord.statusMap.失败 ||*/}
                          {/*                record.status === depositRecord.statusMap.成功 ||*/}
                          {/*                record.status === depositRecord.statusMap.手动成功*/}
                          {/*            }*/}
                          {/*            className={*/}
                          {/*                record.status === depositRecord.statusMap.失败 ||*/}
                          {/*                record.status === depositRecord.statusMap.成功 ||*/}
                          {/*                record.status === depositRecord.statusMap.手动成功*/}
                          {/*                    ? ""*/}
                          {/*                    : "!bg-[#16a34a] !text-slate-50 border-0"*/}
                          {/*            }*/}
                          {/*            onClick={() =>*/}
                          {/*                UpdateModal.confirm({*/}
                          {/*                    title: "是否确定修改状态？",*/}
                          {/*                    resource: "deposits",*/}
                          {/*                    id: record.id,*/}
                          {/*                    values: {*/}
                          {/*                        status: depositRecord.statusMap.手动成功,*/}
                          {/*                    },*/}
                          {/*                })*/}
                          {/*            }*/}
                          {/*        >*/}
                          {/*            成功*/}
                          {/*        </Button>*/}
                          {/*        <Button*/}
                          {/*            style={{*/}
                          {/*                flexGrow: 1,*/}
                          {/*            }}*/}
                          {/*            icon={<CloseOutlined />}*/}
                          {/*            disabled={record.status === depositRecord.statusMap.失败}*/}
                          {/*            className={*/}
                          {/*                record.status === depositRecord.statusMap.失败*/}
                          {/*                    ? ""*/}
                          {/*                    : "!bg-[#ff4d4f] !text-white border-0"*/}
                          {/*            }*/}
                          {/*            onClick={() =>*/}
                          {/*                UpdateModal.confirm({*/}
                          {/*                    title: "是否确定修改状态？",*/}
                          {/*                    resource: "deposits",*/}
                          {/*                    id: record.id,*/}
                          {/*                    values: {*/}
                          {/*                        status: depositRecord.statusMap.失败,*/}
                          {/*                    },*/}
                          {/*                })*/}
                          {/*            }*/}
                          {/*        >*/}
                          {/*            失败*/}
                          {/*        </Button>*/}
                          {/*    </div>*/}
                          {/*)}*/}
                          {record.certificate_files?.map(file => (
                            <Card key={file.id} className="mt-4">
                              <img src={file.url} alt="" />
                            </Card>
                          ))}
                        </>
                      ),
                    });
                  }}
                ></Button>
              </Space>
            );
          },
        },
        {
          title: t('fields.locked'),
          dataIndex: 'locked',
          render(value, record, index) {
            let text = value ? t('status.unlocked') : t('status.locked');
            const { locked, locked_by } = record;
            const notLocker =
              locked && profile?.role !== 1 && profile?.name !== locked_by.name;
            const icon = value ? <LockOutlined /> : <UnlockOutlined />;
            const className = `${
              locked
                ? notLocker
                  ? `!bg-[#bebebe]`
                  : '!bg-black'
                : '!bg-[#ffbe4d]'
            } !text-white border-0`;
            const danger = !value;
            const onClick = () =>
              UpdateModal.confirm({
                title: t('messages.confirmLock', {
                  action: text,
                }),
                resource: 'deposits',
                id: record.id,
                values: {
                  locked: !value,
                },
              });
            return (
              <Space>
                <Button
                  danger={danger}
                  icon={icon}
                  onClick={onClick}
                  disabled={notLocker}
                  className={className}
                ></Button>
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
                            time: dayjs(record.locked_at).format(
                              'YYYY-MM-DD HH:mm:ss'
                            ),
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
        },
        {
          title: t('actions.operation'),
          render(value, record, index) {
            const { locked, locked_by } = record;
            const notLocker =
              locked &&
              profile?.role !== 1 &&
              profile?.name !== locked_by?.name;
            return locked && !notLocker ? (
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
                      onClick={
                        () => {
                          setCurrent(record);
                          showSuccessModal();
                        }
                        // show({
                        //     title: "设置延迟上分时间",
                        //     resource: "deposits",
                        //     id: record.id,
                        //     initialValues: {
                        //         delay_settle_minutes: 0,
                        //     },
                        // })
                        // UpdateModal.confirm({
                        //     title: "是否确定修改状态？",
                        //     resource: "deposits",
                        //     id: record.id,
                        //     values: {
                        //         status: depositRecord.statusMap.手动成功,
                        //     },
                        // })
                      }
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
                          values: {
                            status: Status.失败,
                          },
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
                          formValues: {
                            isEdit: false,
                            to_id: 0,
                          },
                        })
                      }
                    >
                      {t('actions.systemPayout')}
                    </Button>
                  </Space>
                }
                trigger={'click'}
              >
                <Button icon={<SettingOutlined />} type="primary"></Button>
              </Popover>
            ) : (
              <Button disabled icon={<SettingOutlined />}></Button>
            );
          },
        },
        {
          title: t('fields.providerDepositType'),
          dataIndex: 'type',
          render(value, record, index) {
            return value === 3
              ? t('types.generalDeposit')
              : t('types.paufenDeposit');
          },
        },
        {
          title: t('fields.payoutPartyInfo'),
          dataIndex: 'provider',
          render(value: Provider, record, index) {
            return (
              <ShowButton
                icon={null}
                resourceNameOrRouteName="providers"
                recordItemId={value.id}
              >
                {value.name}
              </ShowButton>
            );
          },
        },
        {
          title: t('fields.amount'),
          dataIndex: 'amount',
        },
        {
          title: t('fields.collectionPartyInfo'),
          render(value, record, index) {
            const { bank_card_holder_name, bank_card_number, bank_name } =
              record.from_channel_account;
            return `${bank_card_holder_name} - ${bank_card_number} - ${bank_name}`;
          },
        },
        {
          title: t('fields.orderStatus'),
          dataIndex: 'status',
          render(value, record, index) {
            return getStatusText(value);
          },
        },
        {
          title: t('fields.matchedAt'),
          dataIndex: 'matched_at',
          render(value, record, index) {
            return value ? dayjs(value).format(Format) : null;
          },
        },
        {
          title: t('fields.createdAt'),
          dataIndex: 'created_at',
          render(value, record, index) {
            return dayjs(value).format(Format);
          },
        },
        {
          title: t('fields.successTime'),
          dataIndex: 'confirmed_at',
          render(value, record, index) {
            return value ? dayjs(value).format(Format) : null;
          },
        },
        {
          title: t('fields.systemOrderNumber'),
          dataIndex: 'system_order_number',
        },
        {
          title: t('fields.merchantOrderNumber'),
          dataIndex: 'order_number',
        },
        {
          title: t('fields.userName'),
          dataIndex: 'merchant',
          render(value: Merchant, record, index) {
            return (
              value && (
                <Space>
                  <div className="w-5 h-5 relative">
                    <img
                      src={
                        value?.role !== 3
                          ? '/provider-icon.png'
                          : '/merchant-icon.png'
                      }
                      alt=""
                      className="object-contain"
                      // width={20}
                      // height={20}
                    />
                  </div>
                  <ShowButton
                    icon={null}
                    recordItemId={value?.id}
                    resourceNameOrRouteName={
                      value?.role !== 3 ? 'providers' : 'merchants'
                    }
                  >
                    {value?.name}
                  </ShowButton>
                </Space>
              )
            );
          },
        },
      ],
      filters: [
        {
          field: 'started_at',
          value: defaultStartAt,
          operator: 'eq',
        },
        {
          field: 'confirmed',
          value: 'created',
          operator: 'eq',
        },
      ],
      queryOptions: {
        refetchInterval: enableAuto ? freq * 1000 : undefined,
      },
    });

  const { form: successForm } = useForm();

  const {
    modalProps: successModalProps,
    show: showSuccessModal,
    close: closeSuccessModal,
  } = useModal();
  const { mutateAsync: update } = useUpdate();

  const {
    Modal: UpdateModal,
    show,
    modalProps: updateModalProps,
  } = useUpdateModal({
    resource: 'deposits',
    formItems: [
      {
        label: t('fields.delaySettleMinutes'),
        name: 'delay_settle_minutes',
        children: (
          <Select
            options={[
              {
                label: t('buttons.instant'),
                value: 0,
              },
              {
                label: t('buttons.5min'),
                value: 5,
              },
              {
                label: t('buttons.10min'),
                value: 10,
              },
              {
                label: t('buttons.15min'),
                value: 15,
              },
            ]}
          />
        ),
      },
      {
        label: '',
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input.TextArea />,
        rules: [{ required: true }],
      },
    ],
  });

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={
          <>
            {systemSettings?.data.find(item => item.id === 20)?.enabled ? (
              <ListButton resourceNameOrRouteName="matching-deposit-rewards">
                {t('buttons.quickChargeReward')}
              </ListButton>
            ) : null}
            <ListButton resourceNameOrRouteName="system-bank-cards">
              {t('buttons.generalDepositBankCards')}
            </ListButton>
          </>
        }
      >
        <Form
          initialValues={{
            started_at: dayjs().startOf('days'),
          }}
        />
        <Divider />
        <AutoRefetch />
        <div style={tableOutterStyle}>
          <Table {...tableProps} />
        </div>
      </List>
      <Modal {...updateModalProps} />
      <Modal {...successModalProps} onOk={successForm.submit}>
        <AntdForm
          layout="vertical"
          initialValues={{
            delay_settle_minutes: 0,
            deduct_frozen_balance: false,
          }}
          form={successForm}
          onFinish={async values => {
            await update({
              resource: 'deposits',
              id: current?.id!,
              values: {
                ...values,
                id: current?.id!,
                status: 5,
              },
            });
            closeSuccessModal();
          }}
        >
          <AntdForm.Item
            label={t('fields.delaySettleMinutes')}
            name={'delay_settle_minutes'}
            rules={[{ required: true }]}
          >
            <Select
              options={[
                {
                  label: t('buttons.instant'),
                  value: 0,
                },
                {
                  label: t('buttons.5min'),
                  value: 5,
                },
                {
                  label: t('buttons.10min'),
                  value: 10,
                },
                {
                  label: t('buttons.15min'),
                  value: 15,
                },
              ]}
            />
          </AntdForm.Item>
          {(numeral(current?.provider.wallet.frozen_balance).value() ?? 0) >
            (numeral(current?.amount).value() ?? 0) && (
            <AntdForm.Item
              label={t('fields.deductFrozenBalance')}
              name={'deduct_frozen_balance'}
              valuePropName="checked"
            >
              <Checkbox>
                {t('messages.deductFrozenBalanceTip', {
                  amount: current?.provider.wallet.frozen_balance ?? 0,
                })}
              </Checkbox>
            </AntdForm.Item>
          )}
        </AntdForm>
      </Modal>
    </>
  );
};

export default DepositList;
