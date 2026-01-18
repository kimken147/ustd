import {
  CheckOutlined,
  DollarCircleOutlined,
  EditOutlined,
  StopOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import {
  Button,
  Card,
  Col,
  CreateButton,
  Divider,
  FormItemProps,
  Input,
  InputNumber,
  List,
  ListButton,
  Row,
  Select,
  ShowButton,
  Space,
  Statistic,
  Switch,
  Table,
  Tag,
  TextField,
} from '@pankod/refine-antd';
import { useApiUrl, useCan } from '@pankod/refine-core';
import dayjs from 'dayjs';
import useMerchant from 'hooks/useMerchant';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import { useTagEdit } from 'hooks/useTagEdit';
import useUpdateModal from 'hooks/useUpdateModal';
import { Merchant, Tag as TagModel, Yellow } from '@morgan-ustd/shared';
import React, { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const QueryParams = {
  merchant_name_or_username: 'merchant_name_or_username[]',
  agent_name_or_username: 'agent_name_or_username',
  status: 'status',
  google2fa_enable: 'google2fa_enable',
  balance_limit: 'balance_limit',
  withdraw_fee: 'withdraw_fee',
};

const UpdateMerchantParams = {
  balance_limit: 'balance_limit',
  balance_delta: 'balance_delta',
  type: 'type',
  note: 'note',
  frozen_balance_delta: 'frozen_balance_delta',
  withdraw_fee: 'withdraw_fee',
  status: 'status',
  google2fa_enable: 'google2fa_enable',
  agent_enable: 'agent_enable',
  withdraw_enable: 'withdraw_enable',
  agency_withdraw_enable: 'agency_withdraw_enable',
  withdraw_google2fa_enable: 'withdraw_google2fa_enable',
  transaction_enable: 'transaction_enable',
  third_channel_enable: 'third_channel_enable',
};

export const updateMerchantFormItems: (t: Function) => FormItemProps[] = t => [
  {
    label: t('fields.balanceLimit'),
    name: UpdateMerchantParams.balance_limit,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.type'),
    name: 'type',
    children: (
      <Select
        options={['add', 'minus'].map(type => ({
          label: t(`type.${type}`),
          value: type,
        }))}
      />
    ),
  },
  {
    label: t('wallet.balanceDelta'),
    name: UpdateMerchantParams.balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('wallet.frozenBalanceDelta'),
    name: UpdateMerchantParams.frozen_balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('wallet.withdrawFee'),
    name: QueryParams.withdraw_fee,
    children: <InputNumber className="w-full" />,
    rules: [{ required: true }],
  },
  {
    label: t('fields.note'),
    name: 'note',
    children: <Input.TextArea />,
  },
  {
    label: t('switches.transactionEnable'),
    name: UpdateMerchantParams.transaction_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
  {
    label: t('switches.agencyWithdrawEnable'),
    name: UpdateMerchantParams.agency_withdraw_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
  {
    label: t('switches.withdrawEnable'),
    name: UpdateMerchantParams.withdraw_enable,
    children: (
      <Select
        options={[
          { value: true, label: t('status.on') },
          { value: false, label: t('status.off') },
        ]}
      />
    ),
    rules: [{ required: true }],
  },
];

const MerchantList: FC = () => {
  const apiUrl = useApiUrl();
  const { t } = useTranslation('merchant');
  const { data: canEditWallet } = useCan({
    action: '18',
    resource: 'merchants',
  });
  const { data: canEditProfile } = useCan({
    action: '4',
    resource: 'merchants',
  });
  const { Select: MerchantSelect } = useMerchant({ valueField: 'username' });
  const [selectedKeys, setSelectedKeys] = useState<React.Key[]>([]);

  const { selectProps: selectTagProps } = useSelector<TagModel>({
    resource: 'tags',
  });

  const { Form, tableProps, meta, refetch } = useTable<Merchant>({
    resource: 'merchants',
    formItems: [
      {
        label: t('fields.nameOrAccount'),
        name: QueryParams.merchant_name_or_username,
        children: <MerchantSelect mode="multiple" />,
      },
      {
        label: t('fields.agentNameOrAccount'),
        name: QueryParams.agent_name_or_username,
        children: <Input />,
      },
      {
        label: t('fields.tag'),
        name: 'tag_ids[]',
        children: <Select {...selectTagProps} mode="multiple" />,
      },
    ],
  });

  const { Modal, show } = useUpdateModal({
    formItems: updateMerchantFormItems(t),
    transferFormValues(record) {
      const values = { ...record };
      if (values.balance_delta) {
        values.balance_delta =
          values.type === 'add' ? values.balance_delta : -values.balance_delta;
      }
      if (values.frozen_balance_delta) {
        values.frozen_balance_delta =
          values.type === 'add'
            ? values.frozen_balance_delta
            : -values.frozen_balance_delta;
      }
      return values;
    },
  });

  const { showTagModal, tagModal } = useTagEdit({
    selectTagProps,
    resource: 'merchants',
  });

  return (
    <>
      <Helmet>
        <title>{t('name')}管理</title>
      </Helmet>
      <List
        headerButtons={() => (
          <>
            <ListButton
              icon={<StopOutlined />}
              resourceNameOrRouteName="merchants/banned-list"
            >
              {t('titles.bannedList')}
            </ListButton>
            <ListButton
              icon={<CheckOutlined />}
              resourceNameOrRouteName="merchants/white-list"
            >
              {t('titles.loginWhiteList')}
            </ListButton>
            <ListButton
              icon={<CheckOutlined />}
              resourceNameOrRouteName="merchants/api-white-list"
            >
              {t('titles.apiWhiteList')}
            </ListButton>
            <ListButton
              icon={<WalletOutlined />}
              resourceNameOrRouteName="merchants/wallet-histories"
            >
              {t('titles.walletHistory')}
            </ListButton>
            <CreateButton>{t('actions.create')}</CreateButton>
          </>
        )}
      >
        <Form />
        <Divider />
        <Row>
          <Col xs={24} md={12} lg={6}>
            <Card
              style={{
                border: `2.5px solid ${Yellow}`,
              }}
            >
              <Statistic
                title={t('wallet.totalBalance')}
                value={meta?.total_balance}
                valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
                prefix={<DollarCircleOutlined />}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <div className="mb-4 block">
          {selectedKeys.length ? (
            <Space>
              <Button
                disabled={!canEditProfile?.can}
                onClick={() => {
                  show({
                    title: t('switches.transactionEnable'),
                    filterFormItems: [UpdateMerchantParams.transaction_enable],
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/merchants/${key}`,
                        id: key,
                      })),
                      method: 'put',
                    },
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('batchActions.batchUpdateTransaction')}
              </Button>
              <Button
                disabled={!canEditProfile?.can}
                onClick={() => {
                  show({
                    title: t('switches.agencyWithdrawEnable'),
                    filterFormItems: [
                      UpdateMerchantParams.agency_withdraw_enable,
                    ],
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/merchants/${key}`,
                        id: key,
                      })),
                      method: 'put',
                    },
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('batchActions.batchUpdateAgencyWithdraw')}
              </Button>
              <Button
                disabled={!canEditProfile?.can}
                onClick={() => {
                  show({
                    title: t('switches.withdrawEnable'),
                    filterFormItems: [UpdateMerchantParams.withdraw_enable],
                    customMutateConfig: {
                      mutiple: selectedKeys.map(key => ({
                        url: `${apiUrl}/merchants/${key}`,
                        id: key,
                      })),
                      method: 'put',
                    },
                    onSuccess: () => refetch(),
                  });
                }}
              >
                {t('batchActions.batchUpdateWithdraw')}
              </Button>
              <Button onClick={() => setSelectedKeys([])}>
                {t('batchActions.clearSelection')}
              </Button>
            </Space>
          ) : null}
        </div>
        <Table
          {...tableProps}
          rowSelection={
            canEditProfile?.can
              ? {
                  selectedRowKeys: selectedKeys,
                  onChange: keys => setSelectedKeys(keys),
                  preserveSelectedRowKeys: true,
                }
              : undefined
          }
        >
          <Table.Column<Merchant>
            title={t('fields.name')}
            dataIndex={'name'}
            render={(value, record) => {
              return (
                <ShowButton recordItemId={record.id} icon={null}>
                  {value}
                </ShowButton>
              );
            }}
          />
          <Table.Column<Merchant>
            title={t('fields.tag')}
            dataIndex={'tags'}
            render={(value: TagModel[], record) => (
              <Space>
                <Space wrap>
                  {value.map(tag => (
                    <Tag key={tag.id}>{tag.name}</Tag>
                  ))}
                </Space>
                <Button
                  disabled={!canEditProfile?.can}
                  icon={<EditOutlined className="text-[#6eb9ff]" />}
                  onClick={() => showTagModal(record)}
                />
              </Space>
            )}
          />
          <Table.Column
            title={t('fields.agentName')}
            dataIndex={'agent'}
            render={(agent: Merchant) => {
              return agent ? (
                <ShowButton
                  recordItemId={agent.id}
                  icon={null}
                  resourceNameOrRouteName="merchants"
                >
                  {agent.name}
                </ShowButton>
              ) : (
                t('status.none')
              );
            }}
          />
          <Table.Column<Merchant>
            title={t('fields.balanceLimit')}
            dataIndex={'balance_limit'}
            render={(value, record) => {
              return (
                <Space>
                  <TextField value={value} />
                  <Button
                    icon={<EditOutlined className="text-[#6eb9ff]" />}
                    disabled={!canEditWallet?.can}
                    onClick={() => {
                      show({
                        title: t('fields.balanceLimit'),
                        filterFormItems: [UpdateMerchantParams.balance_limit],
                        id: record.id,
                      });
                    }}
                  />
                </Space>
              );
            }}
          />
          <Table.Column<Merchant>
            title={t('wallet.totalBalance')}
            dataIndex={'wallet'}
            render={(wallet, record) => {
              return (
                <Space>
                  <TextField value={wallet?.balance} />
                  <Button
                    disabled={!canEditWallet?.can}
                    icon={<EditOutlined className="text-[#6eb9ff]" />}
                    onClick={() => {
                      show({
                        title: t('wallet.editTotalBalance'),
                        filterFormItems: [
                          UpdateMerchantParams.type,
                          UpdateMerchantParams.balance_delta,
                          UpdateMerchantParams.note,
                        ],
                        id: record.id,
                      });
                    }}
                  />
                </Space>
              );
            }}
          />
          <Table.Column<Merchant>
            title={t('wallet.frozenBalance')}
            dataIndex={'wallet'}
            render={(wallet, record) => {
              return (
                <Space>
                  <TextField value={wallet?.frozen_balance || '0.00'} />
                  <Button
                    icon={<EditOutlined className="text-[#6eb9ff]" />}
                    disabled={!canEditWallet?.can}
                    onClick={() => {
                      show({
                        title: t('wallet.editFrozenBalance'),
                        filterFormItems: [
                          UpdateMerchantParams.type,
                          UpdateMerchantParams.frozen_balance_delta,
                          UpdateMerchantParams.note,
                        ],
                        id: record.id,
                      });
                    }}
                  />
                </Space>
              );
            }}
          />
          <Table.Column
            title={t('wallet.availableBalance')}
            dataIndex={'wallet'}
            render={wallet => wallet?.available_balance || '0.00'}
          />
          <Table.Column<Merchant>
            title={t('switches.accountStatus')}
            dataIndex={'status'}
            render={(value, record) => (
              <Switch
                disabled={!canEditProfile?.can}
                checked={value}
                onChange={checked => {
                  Modal.confirm({
                    title: t('confirmation.accountStatus'),
                    id: record.id,
                    values: {
                      [UpdateMerchantParams.status]: +checked,
                    },
                  });
                }}
              />
            )}
          />
          <Table.Column<Merchant>
            title={t('switches.transactionEnable')}
            dataIndex={'transaction_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEditProfile?.can}
                checked={value}
                onChange={checked => {
                  Modal.confirm({
                    title: t('confirmation.transactionEnable'),
                    id: record.id,
                    values: {
                      [UpdateMerchantParams.transaction_enable]: +checked,
                    },
                  });
                }}
              />
            )}
          />
          <Table.Column<Merchant>
            title={t('switches.withdrawEnable')}
            dataIndex={'withdraw_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEditProfile?.can}
                checked={value}
                onChange={checked => {
                  Modal.confirm({
                    title: t('confirmation.withdrawEnable'),
                    id: record.id,
                    values: {
                      [UpdateMerchantParams.withdraw_enable]: +checked,
                    },
                  });
                }}
              />
            )}
          />
          <Table.Column<Merchant>
            title={t('switches.agencyWithdrawEnable')}
            dataIndex={'agency_withdraw_enable'}
            render={(value, record) => (
              <Switch
                disabled={!canEditProfile?.can}
                checked={value}
                onChange={checked => {
                  Modal.confirm({
                    title: t('confirmation.agencyWithdrawEnable'),
                    id: record.id,
                    values: {
                      [UpdateMerchantParams.agency_withdraw_enable]: +checked,
                    },
                  });
                }}
              />
            )}
          />
          <Table.Column<Merchant>
            title={t('fields.lastLoginTime')}
            dataIndex={'last_login_at'}
            render={value =>
              value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : null
            }
          />
          <Table.Column
            title={t('fields.lastLoginIp')}
            dataIndex={'last_login_ipv4'}
            render={value => value}
          />
          <Table.Column<Merchant>
            title={t('actions.delete')}
            render={(_, record) => {
              return (
                <Space>
                  <Button
                    disabled={!canEditProfile?.can}
                    danger
                    onClick={() =>
                      Modal.confirm({
                        id: record.id,
                        values: {
                          id: record.id,
                        },
                        mode: 'delete',
                        title: t('messages.deleteConfirm'),
                      })
                    }
                  >
                    {t('actions.delete')}
                  </Button>
                </Space>
              );
            }}
          />
        </Table>
      </List>
      <Modal
        defaultValue={{
          [UpdateMerchantParams.balance_limit]: 0,
          [UpdateMerchantParams.balance_delta]: 0,
          [UpdateMerchantParams.type]: 'add',
        }}
      />
      {tagModal}
    </>
  );
};

export default MerchantList;
