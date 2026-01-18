import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  DollarCircleOutlined,
  EditOutlined,
  InfoCircleOutlined,
  ReloadOutlined,
} from '@ant-design/icons';
import {
  CreateButton,
  List,
  TextField,
  ShowButton,
} from '@refinedev/antd';
import {
  Button,
  Divider,
  Input,
  InputNumber,
  Modal,
  Popover,
  Select,
  Space,
  Switch,
  Modal as AntdModal,
  Row,
  Col,
  Statistic,
  Card,
} from 'antd';
import {
  useApiUrl,
  useCan,
  useDelete,
  useNotification,
  useUpdate,
} from '@refinedev/core';
import Badge from 'components/badge';
import Table from 'components/table';
import dayjs from 'dayjs';
import 'dayjs/plugin/relativeTime';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useBank from 'hooks/useBank';
import useChannelAmounts from 'hooks/useChannelAmounts';
import useProvider from 'hooks/useProvider';
import useRegion from 'hooks/useRegion';
import useSystemSetting from 'hooks/useSystemSetting';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import useUserChannelAccount from 'hooks/useUserChannelAccount';
import useUserChannelStatus from 'hooks/useUserChannelStatus';
import {
  SelectOption,
  ProviderUserChannel as UserChannel,
  Meta,
  UserChannelStatus,
  UserChannelType,
  AccountStatus,
  SyncStatus,
  Gray,
  Green,
  Purple,
  Red,
  Yellow,
  Resource,
} from '@morgan-ustd/shared';
import useChannelStatus from 'hooks/useChannelStatus';
import Enviroment from 'lib/env';
import { intersectionWith, sumBy } from 'lodash';
import numeral from 'numeral';
import { FC, ReactNode, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

type StatusOptions = {
  text: string;
  color: string;
};

const UserChannelAccountList: FC = () => {
  const { t } = useTranslation('userChannel');
  const { getChannelStatusText, getChannelTypeText } = useChannelStatus();
  const isPaufen = Enviroment.isPaufen;
  const name = isPaufen ? t('fields.providerName') : t('fields.groupName');
  const apiUrl = useApiUrl();
  const region = useRegion();
  const { Select: UserChannelAccountStatusSelect } = useUserChannelStatus();
  const { data: canEdit } = useCan({
    action: '5',
    resource: 'user-channel-accounts',
  });
  const { data: canDelete } = useCan({
    action: '6',
    resource: 'user-channel-accounts',
  });
  const { data: providers, Select: ProviderSelect } = useProvider();
  const { data: banks } = useBank();
  const { data: channelAmounts } = useChannelAmounts();
  const { data: userChannelAccounts } = useUserChannelAccount();

  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const {
    Form: QueryForm,
    refetch,
    meta,
    data,
    tableProps,
  } = useTable<UserChannel, Meta>({
    formItems: [
      {
        label: name,
        name: 'name_or_username',
        children: (
          <Select
            options={providers?.map<SelectOption>(provider => ({
              label: provider.name,
              value: provider.name,
              key: String(provider.id),
            }))}
            optionFilterProp="label"
            showSearch
          />
        ),
      },
      // {
      //     label: "上级代理名称或登录帐号",
      //     name: "agent_name_or_username",
      //     children: <Input allowClear />,
      // },
      {
        label: t('fields.channel'),
        name: 'channel_code[]',
        children: (
          <Select
            allowClear
            mode="multiple"
            options={channelAmounts?.map(channel => ({
              value: channel.channel_code,
              label: channel.name,
              key: channel.id,
            }))}
            optionFilterProp="label"
          />
        ),
      },
      {
        label: t('fields.type'),
        name: 'type[]',
        children: (
          <Select
            allowClear
            mode="multiple"
            options={[
              {
                value: UserChannelType.收出款,
                label: getChannelTypeText(UserChannelType.收出款),
              },
              {
                value: UserChannelType.收款,
                label: getChannelTypeText(UserChannelType.收款),
              },
              {
                value: UserChannelType.出款,
                label: getChannelTypeText(UserChannelType.出款),
              },
            ]}
          />
        ),
        hidden: true,
      },
      {
        label: t('fields.status'),
        name: 'status[]',
        children: (
          <Select
            allowClear
            mode="multiple"
            options={[
              {
                value: UserChannelStatus.强制下线,
                label: getChannelStatusText(UserChannelStatus.强制下线),
              },
              {
                value: UserChannelStatus.下线,
                label: getChannelStatusText(UserChannelStatus.下线),
              },
              {
                value: UserChannelStatus.上线,
                label: getChannelStatusText(UserChannelStatus.上线),
              },
            ]}
          />
        ),
      },
      {
        label: t('fields.accountName'),
        name: 'account_name',
        children: <Input />,
      },
      {
        label: t('fields.bankName'),
        name: 'bank[]',
        children: (
          <Select
            optionFilterProp="label"
            mode="multiple"
            options={banks?.map(bank => ({
              value: bank.id,
              label: bank.name,
              key: bank.id,
            }))}
          />
        ),
      },
      {
        label: t('fields.account'),
        name: 'name[]',
        children: (
          <Select
            options={userChannelAccounts?.map<SelectOption>(
              userChannelAccount => ({
                label: `${userChannelAccount.account}(${userChannelAccount.name})`,
                value: userChannelAccount.name,
              })
            )}
            showSearch
            optionFilterProp="label"
            mode="multiple"
          />
        ),
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
    ],
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });
  const { show: showUpdateModal, modalProps } = useUpdateModal({
    formItems: [
      {
        label: name,
        name: 'provider_id',
        children: <ProviderSelect />,
      },
      {
        label: t('fields.balance'),
        name: 'balance',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.balanceLimit'),
        name: 'balance_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.mpin'),
        name: 'mpin',
        children: <Input />,
      },
      {
        label: t('fields.dailyLimitReceive'),
        name: 'daily_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.dailyLimitPayout'),
        name: 'withdraw_daily_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.monthlyLimitReceive'),
        name: 'monthly_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.monthlyLimitPayout'),
        name: 'withdraw_monthly_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.singleMinLimit'),
        name: 'single_min_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.singleMaxLimit'),
        name: 'single_max_limit',
        children: <InputNumber className="w-full" />,
      },
      {
        name: 'allow_unlimited',
        // children: <><Checkbox /> 允许无限制</>,
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
      {
        label: t('fields.status'),
        name: 'status',
        children: (
          <Select
            options={[
              {
                value: UserChannelStatus.强制下线,
                label: getChannelStatusText(UserChannelStatus.强制下线),
              },
              {
                value: UserChannelStatus.下线,
                label: getChannelStatusText(UserChannelStatus.下线),
              },
              {
                value: UserChannelStatus.上线,
                label: getChannelStatusText(UserChannelStatus.上线),
              },
            ]}
          />
        ),
        rules: [{ required: true }],
      },
      {
        label: t('fields.type'),
        name: 'type',
        children: <UserChannelAccountStatusSelect />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.newPassword'),
        name: 'newPassword',
        children: <Input />,
      },
      {
        label: t('fields.newEmail'),
        name: 'newEmail',
        children: <Input type="email" />,
      },
    ],
  });
  const { mutateAsync: mutataUpdating } = useUpdate();
  const { mutateAsync: mutateDeleting } = useDelete();

  const mutateUserChannel = async ({
    record,
    values,
    title = t('confirmation.modify'),
    method = 'put',
  }: {
    record: UserChannel;
    values: Partial<IUpdateUserChannel>;
    title?: string;
    method?: 'put' | 'delete';
  }) => {
    Modal.confirm({
      title,
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        method === 'put'
          ? mutataUpdating({
              resource: Resource.userChannelAccounts,
              id: record.id,
              values: {
                ...values,
                id: record.id,
              },
              successNotification: {
                message: t('messages.updateSuccess'),
                type: 'success',
              },
            })
          : mutateDeleting({
              resource: Resource.userChannelAccounts,
              id: record.id,
              successNotification: {
                message: t('messages.deleteSuccess'),
                type: 'success',
              },
            });
      },
    });
  };

  const { data: systemSetting } = useSystemSetting();
  const dayEnable = systemSetting?.find(x => x.id === 35)?.enabled;
  const monthEnable = systemSetting?.find(x => x.id === 45)?.enabled;

  const { open } = useNotification();
  const [selectedKeys, setSelectedKeys] = useState<React.Key[]>([]);

  return (
    <>
      <Helmet>
        <title>{t('titles.pageTitle')}</title>
      </Helmet>
      <List
        headerButtons={() => (
          <>
            <CreateButton>{t('actions.create')}</CreateButton>
          </>
        )}
      >
        <QueryForm />
        <Divider />
        <Row>
          <Col xs={24} md={12} lg={6}>
            <Card
              bordered
              style={{
                border: `2.5px solid ${Yellow}`,
              }}
            >
              <Statistic
                title={t('fields.totalBalance')}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
                prefix={<DollarCircleOutlined />}
                value={
                  selectedKeys?.length
                    ? numeral(
                        sumBy(
                          intersectionWith(
                            data,
                            selectedKeys,
                            (a, b) => a.id === b
                          ),
                          a => +a.balance
                        )
                      ).format('0,0.00')
                    : meta?.total_balance
                }
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <AutoRefetch />
        <div className="mb-4 block">
          {selectedKeys.length ? (
            <Space>
              <Button
                disabled={!canEdit?.can}
                onClick={() => {
                  showUpdateModal({
                    title: t('actions.batchEditBalanceLimit'),
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/user-channel-accounts/${key}`,
                        id: key as string | number,
                      })),
                      method: 'put',
                    },
                    filterFormItems: ['balance_limit'],
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('actions.batchEditBalanceLimit')}
              </Button>
              <Button
                disabled={!canEdit?.can}
                onClick={() => {
                  showUpdateModal({
                    title: t('actions.batchEditStatus'),
                    filterFormItems: ['status'],
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/user-channel-accounts/${key}`,
                        id: key as string | number,
                      })),
                      method: 'put',
                    },
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('actions.batchEditStatus')}
              </Button>
              <Button
                disabled={!canEdit?.can}
                onClick={() => {
                  showUpdateModal({
                    title: t('actions.batchEditSingleLimit'),
                    initialValues: {
                      allow_unlimited: true,
                    },
                    filterFormItems: [
                      'single_min_limit',
                      'single_max_limit',
                      'allow_unlimited',
                    ],
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/user-channel-accounts/${key}`,
                        id: key as string | number,
                      })),
                      method: 'put',
                    },
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('actions.batchEditSingleLimit')}
              </Button>
              {/* <Button
                                disabled={!canEdit?.can}
                                onClick={() => {
                                    showUpdateModal({
                                        title: "批量修改类型",
                                        filterFormItems: ["type"],
                                        customMutateConfig: {
                                            mutiple: selectedKeys.map((key) => ({
                                                url: `${apiUrl}/user-channel-accounts/${key}`,
                                                id: key,
                                            })),
                                            method: "put",
                                        },
                                        onSuccess: () => refetch(),
                                    });
                                }}
                            >
                                批量修改类型
                            </Button> */}
              {/* <Button
                                disabled={!canEdit?.can}
                                onClick={() => {
                                    showUpdateModal({
                                        title: "批量修改密码",
                                        filterFormItems: ["newPassword"],
                                        customMutateConfig: {
                                            url: `${process.env.REACT_APP_HOST}/api/v1/maya/change-password`,
                                            values: {
                                                ids: selectedKeys,
                                            },
                                            method: "put",
                                        },
                                        // resource: "maya/change-password",
                                        onSuccess: () => refetch(),
                                        // formValues: {
                                        //     ids: selectedKeys,
                                        // },
                                    });
                                }}
                            >
                                批量修改密码
                            </Button> */}
              {/* <Button
                                disabled={!canEdit?.can}
                                onClick={() => {
                                    showUpdateModal({
                                        title: "批量修改Email",
                                        filterFormItems: ["newEmail"],
                                        customMutateConfig: {
                                            url: `${process.env.REACT_APP_HOST}/api/v1/maya/change-email`,
                                            values: {
                                                ids: selectedKeys,
                                            },
                                            method: "put",
                                        },
                                        // resource: "maya/change-password",
                                        onSuccess: () => refetch(),
                                        // formValues: {
                                        //     ids: selectedKeys,
                                        // },
                                    });
                                }}
                            >
                                批量修改Email
                            </Button> */}
              <Button
                danger
                onClick={() =>
                  AntdModal.confirm({
                    title: t('confirmation.batchDelete'),
                    okText: t('actions.ok'),
                    cancelText: t('actions.cancel'),
                    onOk: async () => {
                      const promises: Promise<any>[] = [];
                      for (let key of selectedKeys) {
                        promises.push(
                          mutateDeleting({
                            resource: Resource.userChannelAccounts,
                            id: key as string | number,
                            successNotification: false,
                          })
                        );
                      }
                      try {
                        await Promise.all(promises);
                        open?.({
                          message: t('messages.batchDeleteSuccess'),
                          type: 'success',
                        });
                      } catch (error) {}
                    },
                  })
                }
              >
                {t('actions.batchDelete')}
              </Button>
              <Button
                onClick={() => {
                  setSelectedKeys([]);
                }}
              >
                {t('actions.clearAll')}
              </Button>
            </Space>
          ) : null}
        </div>
        <Table
          {...tableProps}
          rowSelection={
            canEdit?.can
              ? {
                  selectedRowKeys: selectedKeys,
                  onChange: keys => setSelectedKeys(keys),
                  preserveSelectedRowKeys: true,
                }
              : undefined
          }
        >
          <Table.Column<UserChannel>
            render={(_, record) => {
              return (
                <Space>
                  {isPaufen ? (
                    <ShowButton
                      icon={null}
                      recordItemId={record.user.id}
                      resourceNameOrRouteName="providers"
                    >
                      {record.user.name}
                    </ShowButton>
                  ) : (
                    <>
                      <TextField value={record.user.name} />
                      <Button
                        icon={
                          <EditOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        disabled={!canEdit?.can}
                        onClick={() => {
                          showUpdateModal({
                            title: t('actions.editProvider', { name }),
                            filterFormItems: ['provider_id'],
                            initialValues: {
                              provider_id: record.user.id,
                            },
                            id: record.id,
                          });
                        }}
                      ></Button>
                    </>
                  )}
                </Space>
              );
            }}
            title={name}
            width={120}
          />
          <Table.Column
            dataIndex={'channel_name'}
            title={t('fields.channel')}
            render={(value: string) => {
              return value;
              // .split("")
              // .filter(
              //     (char) => !Number.isInteger(+char) && char !== "~" && char !== "." && char !== ",",
              // );
            }}
          />
          <Table.Column<UserChannel>
            dataIndex={'status'}
            title={
              <Space>
                <TextField value={t('fields.status')} />
                {/* <Button
                                    icon={<EditOutlined style={{ color: canEdit?.can ? Purple : Gray }} />}
                                    disabled={!canEdit?.can}
                                    onClick={() =>
                                        showUpdateModal({
                                            title: "批量修改连线状态",
                                            filterFormItems: ["status"],
                                            customMutateConfig: {
                                                url: `${apiUrl}/user-channel-accounts/undefined`,
                                                values: {
                                                    all: true,
                                                },
                                                method: "put",
                                            },
                                            onSuccess: () => refetch(),
                                        })
                                    }
                                /> */}
              </Space>
            }
            render={(value, record) => {
              const options: StatusOptions = {
                text: t('status.online'),
                color: '#16a34a',
              };
              if (value === 0) {
                options.color = '#bebebe';
              } else if (value === 1) {
                options.color = '#ff4d4f';
              }
              options.text = getChannelStatusText(value);
              return (
                <Space>
                  <Badge color={options.color} text={options.text} />
                  {canEdit?.can ? (
                    <Popover
                      trigger={'click'}
                      content={
                        <ul className="popover-edit-list">
                          {[0, 1, 2]
                            .filter(status => status !== value)
                            .map(status => (
                              <li
                                key={status}
                                onClick={() => {
                                  mutateUserChannel({
                                    record,
                                    values: {
                                      status,
                                      id: record.id,
                                    },
                                    title: t('confirmation.changeStatus'),
                                  });
                                }}
                              >
                                {getChannelStatusText(status)}
                              </li>
                            ))}
                        </ul>
                      }
                    >
                      <Button
                        icon={<EditOutlined className="text-[#6eb9ff]" />}
                      />
                    </Popover>
                  ) : (
                    <Button icon={<EditOutlined />} disabled />
                  )}
                </Space>
              );
            }}
          />
          {region !== 'CN' ? (
            <Table.Column<UserChannel>
              dataIndex={'type'}
              title={t('fields.type')}
              render={(value, record) => (
                <Space>
                  <TextField value={getChannelTypeText(value)} />
                  {canEdit?.can ? (
                    <Popover
                      trigger={'click'}
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
                                    values: {
                                      type,
                                    },
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
                      <Button
                        icon={<EditOutlined className="text-[#6eb9ff]" />}
                      />
                    </Popover>
                  ) : (
                    <Button disabled icon={<EditOutlined />} />
                  )}
                </Space>
              )}
            />
          ) : null}
          <Table.Column<UserChannel>
            render={(_, record) => {
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
              } else if (account_status === 'fail')
                icon = <CloseCircleOutlined style={{ color: Red }} />;
              else if (account_status === 'unverified')
                icon = <InfoCircleOutlined style={{ color: Yellow }} />;
              const renderItem =
                region === 'CN' ? null : (
                  <>
                    {mpin}
                    <Button
                      disabled={!canEdit?.can}
                      icon={<EditOutlined />}
                      onClick={() =>
                        showUpdateModal({
                          title: t('actions.editMpin'),
                          filterFormItems: ['mpin'],
                          id: record.id,
                          initialValues: {
                            mpin,
                          },
                        })
                      }
                      style={{
                        color: canEdit?.can ? Purple : Gray,
                      }}
                    />
                    <Popover
                      content={
                        <TextField value={AccountStatus[account_status]} />
                      }
                    >
                      {icon}
                    </Popover>
                  </>
                );
              return (
                <>
                  <div>
                    <Space>
                      {/* <TextField value={`${record.account} ${mpin ? `(${mpin})` : ""}`} /> */}
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
            }}
            title={t('fields.account')}
          />
          <Table.Column<UserChannel>
            dataIndex="bank_name"
            title={t('fields.bankName')}
          />
          <Table.Column<UserChannel>
            dataIndex="bank_branch"
            title={t('fields.bankBranch')}
            render={value => {
              if (value === 'undefined' || !value) return '';
              return value;
            }}
          />
          <Table.Column<UserChannel>
            dataIndex={['detail', 'bank_card_holder_name']}
            title={t('fields.bankCardHolder')}
          />
          <Table.Column<UserChannel>
            dataIndex="note"
            title={t('fields.note')}
            render={(value, record) => {
              return (
                <Space>
                  <TextField value={value} />
                  <EditOutlined
                    style={{ color: Purple }}
                    onClick={() =>
                      showUpdateModal({
                        title: t('actions.editNote'),
                        id: record.id,
                        filterFormItems: ['note'],
                        initialValues: {
                          note: value,
                        },
                      })
                    }
                  />
                </Space>
              );
            }}
          />
          <Table.Column
            dataIndex={'id'}
            title={t('fields.accountNumber')}
            render={value => `${numeral(value).format('00000')}`}
          />
          <Table.Column<UserChannel>
            dataIndex={'balance'}
            title={t('fields.balance')}
            render={(value, record) => {
              return (
                <Space>
                  <TextField value={value} />
                  <Button
                    icon={
                      <EditOutlined
                        className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                      />
                    }
                    onClick={() => {
                      showUpdateModal({
                        title: t('actions.editBalance'),
                        id: record.id,
                        initialValues: {
                          balance: record.balance,
                        },
                        filterFormItems: ['balance'],
                      });
                    }}
                    disabled={!canEdit?.can}
                  />
                </Space>
              );
            }}
          />
          <Table.Column<UserChannel>
            dataIndex={'balance_limit'}
            title={t('fields.balanceLimit')}
            render={(value, record) => {
              return (
                <Space>
                  <TextField value={value} />
                  <Button
                    disabled={!canEdit?.can}
                    icon={
                      <EditOutlined
                        className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                      />
                    }
                    onClick={() => {
                      showUpdateModal({
                        id: record.id,
                        initialValues: {
                          balance_limit: value,
                        },
                        filterFormItems: ['balance_limit'],
                        title: t('actions.editBalanceLimit'),
                      });
                    }}
                  />
                </Space>
              );
            }}
          />
          <Table.Column<UserChannel>
            dataIndex={'single_limit'}
            title={t('fields.singleLimitReceive')}
            render={(value, record) => {
              let singleLimit = '';
              let singleLimitAll = '';
              let singleLimitStyle = {
                paddingLeft: '15px',
                paddingRight: '15px',
              };

              if (
                !(
                  record.single_min_limit === null ||
                  record.single_min_limit === undefined
                )
              ) {
                singleLimit =
                  numeral(record.single_min_limit).format('0,0.00') + '~';
                singleLimitStyle.paddingLeft = '0px';
              }

              if (
                !(
                  record.single_max_limit === null ||
                  record.single_max_limit === undefined
                )
              ) {
                if (singleLimit === '') {
                  singleLimit =
                    '~' + numeral(record.single_max_limit).format('0,0.00');
                } else {
                  singleLimit += numeral(record.single_max_limit).format(
                    '0,0.00'
                  );
                }

                singleLimitStyle.paddingLeft = '0px';
              }

              if (singleLimit !== ' ') {
                singleLimitAll = singleLimit;
              }

              if (
                record.single_min_limit === null &&
                record.withdraw_single_min_limit === null
              ) {
                singleLimitStyle.paddingLeft = '0px';
                singleLimitStyle.paddingRight = '0px';
              }

              return (
                <Space>
                  <TextField value={singleLimitAll} style={singleLimitStyle} />
                  <Button
                    disabled={!canEdit?.can}
                    icon={
                      <EditOutlined
                        className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                      />
                    }
                    onClick={() => {
                      showUpdateModal({
                        id: record.id,
                        initialValues: {
                          single_min_limit: record.single_min_limit,
                          single_max_limit: record.single_max_limit,
                          allow_unlimited: true,
                        },
                        filterFormItems: [
                          'single_min_limit',
                          'single_max_limit',
                          'allow_unlimited',
                        ],
                        title: t('actions.editSingleLimit'),
                      });
                    }}
                  />
                </Space>
              );
            }}
          />

          {dayEnable ? (
            <>
              <Table.Column<UserChannel>
                dataIndex={'daily_status'}
                title={t('switches.dailyLimitSwitch')}
                render={(value, record) => (
                  <Switch
                    disabled={!canEdit?.can}
                    checked={value}
                    onChange={value => {
                      mutateUserChannel({
                        record,
                        values: {
                          daily_status: value,
                        },
                      });
                    }}
                  />
                )}
              />
              <Table.Column<UserChannel>
                title={t('fields.dailyLimitReceiveUsed')}
                render={(_, record) => {
                  return (
                    <Space>
                      <TextField
                        value={`${numeral(record.daily_limit).format('0,0.00')} / ${
                          record.daily_total
                        }`}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <EditOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          showUpdateModal({
                            title: t('actions.editDailyLimitReceive'),
                            id: record.id,
                            initialValues: {
                              daily_limit: record.daily_limit,
                            },
                            filterFormItems: ['daily_limit'],
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <ReloadOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              daily_total: 0,
                            },
                            title: t('confirmation.resetDailyUsedReceive'),
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <CloseCircleOutlined
                            className={canEdit?.can ? 'text-[#ff4d4f]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              daily_limit_null: true,
                            },
                            title: t(
                              'confirmation.resetDailyLimitDefaultReceive'
                            ),
                          });
                        }}
                      />
                    </Space>
                  );
                }}
              />
              <Table.Column<UserChannel>
                title={t('fields.dailyLimitPayoutUsed')}
                render={(_, record) => {
                  return (
                    <Space>
                      <TextField
                        value={`${numeral(record.withdraw_daily_limit).format('0,0.00')} / ${
                          record.withdraw_daily_total
                        }`}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <EditOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          showUpdateModal({
                            title: t('actions.editDailyLimitPayout'),
                            id: record.id,
                            initialValues: {
                              withdraw_daily_limit: record.withdraw_daily_limit,
                            },
                            filterFormItems: ['withdraw_daily_limit'],
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <ReloadOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              withdraw_daily_total: 0,
                            },
                            title: t('confirmation.resetDailyUsedPayout'),
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <CloseCircleOutlined
                            className={canEdit?.can ? 'text-[#ff4d4f]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              daily_withdraw_limit_null: true,
                            },
                            title: t(
                              'confirmation.resetDailyLimitDefaultPayout'
                            ),
                          });
                        }}
                      />
                    </Space>
                  );
                }}
              />
            </>
          ) : null}
          {monthEnable ? (
            <>
              <Table.Column<UserChannel>
                dataIndex={'monthly_status'}
                title={t('switches.monthlyLimitSwitch')}
                render={(value, record) => (
                  <Switch
                    disabled={!canEdit?.can}
                    checked={value}
                    onChange={value => {
                      mutateUserChannel({
                        record,
                        values: {
                          monthly_status: value,
                        },
                      });
                    }}
                  />
                )}
              />
              <Table.Column<UserChannel>
                title={t('fields.monthlyLimitReceiveUsed')}
                render={(_, record) => {
                  return (
                    <Space>
                      <TextField
                        value={`${numeral(record.monthly_limit).format('0,0.00')} / ${
                          record.monthly_total
                        }`}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <EditOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          showUpdateModal({
                            title: t('actions.editMonthlyLimitReceive'),
                            id: record.id,
                            initialValues: {
                              monthly_limit: record.monthly_limit,
                            },
                            filterFormItems: ['monthly_limit'],
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <ReloadOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              monthly_total: 0,
                            },
                            title: t('confirmation.resetMonthlyUsedReceive'),
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <CloseCircleOutlined
                            className={canEdit?.can ? 'text-[#ff4d4f]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              monthly_limit_null: true,
                            },
                            title: t(
                              'confirmation.resetMonthlyLimitDefaultReceive'
                            ),
                          });
                        }}
                      />
                    </Space>
                  );
                }}
              />
              <Table.Column<UserChannel>
                title={t('fields.monthlyLimitPayoutUsed')}
                render={(_, record) => {
                  return (
                    <Space>
                      <TextField
                        value={`${numeral(record.withdraw_monthly_limit).format('0,0.00')} / ${
                          record.withdraw_monthly_total
                        }`}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <EditOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          showUpdateModal({
                            title: t('actions.editMonthlyLimitPayout'),
                            id: record.id,
                            initialValues: {
                              withdraw_monthly_limit:
                                record.withdraw_monthly_limit,
                            },
                            filterFormItems: ['withdraw_monthly_limit'],
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <ReloadOutlined
                            className={canEdit?.can ? 'text-[#6eb9ff]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              withdraw_monthly_total: 0,
                            },
                            title: t('confirmation.resetMonthlyUsedPayout'),
                          });
                        }}
                      />
                      <Button
                        disabled={!canEdit?.can}
                        icon={
                          <CloseCircleOutlined
                            className={canEdit?.can ? 'text-[#ff4d4f]' : ''}
                          />
                        }
                        onClick={() => {
                          mutateUserChannel({
                            record,
                            values: {
                              monthly_withdraw_limit_null: true,
                            },
                            title: t(
                              'confirmation.resetMonthlyLimitDefaultPayout'
                            ),
                          });
                        }}
                      />
                    </Space>
                  );
                }}
              />
              <Table.Column<UserChannel>
                title={t('fields.operation')}
                render={(value, record) => (
                  <Space>
                    <Button
                      disabled={!canDelete?.can}
                      danger
                      className={canDelete?.can ? '!text-[#ff4d4f]' : ''}
                      onClick={() => {
                        Modal.confirm({
                          title: t('confirmation.deleteAccount'),
                          okText: t('actions.ok'),
                          cancelText: t('actions.cancel'),
                          onOk: () => {
                            mutateDeleting({
                              resource: Resource.userChannelAccounts,
                              id: record.id,
                            });
                          },
                        });
                      }}
                    >
                      {t('actions.delete')}
                    </Button>
                  </Space>
                )}
              />
            </>
          ) : null}
        </Table>
      </List>
      {/* <UpdateModal /> */}
      <AntdModal {...modalProps} />
    </>
  );
};

export default UserChannelAccountList;
