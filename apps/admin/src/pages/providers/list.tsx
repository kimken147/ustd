import {
  EditOutlined,
  LoginOutlined,
  PlusSquareOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import {
  Button,
  CreateButton,
  DeleteButton,
  Divider,
  FormItemProps,
  Input,
  InputNumber,
  List,
  ListButton,
  Modal,
  Select,
  ShowButton,
  Space,
  Switch,
  Table,
  TableColumnProps,
  Tag,
  TextField,
} from '@refinedev/antd';
import dayjs from 'dayjs';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import { useTagEdit } from 'hooks/useTagEdit';
import useUpdateModal from 'hooks/useUpdateModal';
import { Provider } from 'interfaces/provider';
import { getStatusOptions } from 'lib/status';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { Tag as TagModel } from '@morgan-ustd/shared';
import { useTranslation } from 'react-i18next';

const QueryParams = {
  merchant_name_or_username: 'merchant_name_or_username[]',
  agent_name_or_username: 'agent_name_or_username',
  status: 'status',
  google2fa_enable: 'google2fa_enable',
  balance_limit: 'balance_limit',
  withdraw_fee: 'withdraw_fee',
};

const UpdateProviderParams = {
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
  profit_delta: 'profit_delta',
  deposit_enable: 'deposit_enable',
  paufen_deposit_enable: 'paufen_deposit_enable',
};

export const updateMerchantFormItems: (t: Function) => FormItemProps[] = t => [
  {
    label: t('fields.balanceLimit'),
    name: UpdateProviderParams.balance_limit,
    children: <InputNumber className="w-full" />,
    rules: [
      {
        required: true,
      },
    ],
  },
  {
    label: t('type', {
      ns: 'common',
    }),
    name: 'type',
    children: (
      <Select
        options={['add', 'minus'].map(type => ({
          label:
            type === 'add'
              ? t('add', {
                  ns: 'common',
                })
              : t('minus', {
                  ns: 'common',
                }),
          value: type,
        }))}
      />
    ),
  },
  {
    label: t('fields.totalBalance'),
    name: UpdateProviderParams.balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [
      {
        required: true,
      },
    ],
  },
  {
    label: t('fields.frozenBalance'),
    name: UpdateProviderParams.frozen_balance_delta,
    children: <InputNumber className="w-full" />,
    rules: [
      {
        required: true,
      },
    ],
  },
  {
    label: t('wallet.withdrawFee', {
      ns: 'common',
    }),
    name: QueryParams.withdraw_fee,
    children: <InputNumber className="w-full" />,
    rules: [
      {
        required: true,
      },
    ],
  },
  {
    label: t('fields.profit'),
    name: UpdateProviderParams.profit_delta,
    children: <InputNumber className="w-full" />,
    rules: [
      {
        required: true,
      },
    ],
  },
  {
    label: t('note', {
      ns: 'common',
    }),
    name: 'note',
    children: <Input.TextArea />,
  },
];

const ProvidersList: FC = () => {
  const { t } = useTranslation('providers');
  const { Select: ProviderSelect } = useSelector<Provider>({
    resource: 'providers',
    valueField: 'name',
  });
  const { selectProps: selectTagProps } = useSelector<TagModel>({
    resource: 'tags',
  });

  const { Form, tableProps } = useTable<Provider>({
    formItems: [
      {
        label: t('filters.nameOrAccount'),
        name: 'provider_name_or_username',
        children: <ProviderSelect />,
      },
      {
        label: t('filters.agentOrAccount'),
        name: 'agent_name_or_username',
        children: <Input />,
      },
      {
        label: t('filters.accountStatus'),
        name: 'status',
        children: <Select options={getStatusOptions()} />,
      },
      {
        label: t('switches.transactionEnable'),
        name: 'transaction_enable',
        children: <Select options={getStatusOptions()} />,
      },
      {
        label: t('fields.tag'),
        name: 'tag_ids[]',
        children: <Select {...selectTagProps} mode="multiple" />,
      },
      {
        label: t('switches.google2faEnable'),
        name: 'google2fa_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      {
        label: t('switches.depositEnable'),
        name: 'deposit_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      {
        label: t('switches.withdrawEnable'),
        name: 'withdraw_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      {
        label: t('switches.verifyMoneyIn'),
        name: 'cancel_order_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      // {
      //     label: "站内转点",
      //     name: "balance_transfer_enable",
      //     children: <Select options={getStatusOptions()} />,
      //     collapse: true,
      // },
      {
        label: t('switches.paufenDepositEnable'),
        name: 'paufen_deposit_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      {
        label: t('switches.withdrawProfit'),
        name: 'withdraw_profit_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
      {
        label: t('switches.agentEnable'),
        name: 'agent_enable',
        children: <Select options={getStatusOptions()} />,
        collapse: true,
      },
    ],
  });

  const {
    modalProps,
    show,
    Modal: UpdateModal,
  } = useUpdateModal({
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
      if (values.profit_delta) {
        values.profit_delta =
          values.type === 'add' ? values.profit_delta : -values.profit_delta;
      }
      return values;
    },
  });

  const columns: TableColumnProps<Provider>[] = [
    {
      title: t('fields.name'),
      dataIndex: 'name',
      render(value, record, index) {
        return (
          <ShowButton icon={null} recordItemId={record.id.toString()}>
            {value}
          </ShowButton>
        );
      },
    },
    {
      title: t('fields.tag'),
      dataIndex: 'tags',
      render(value: TagModel[], record, index) {
        return (
          <Space>
            <Space wrap>
              {value.map(tag => (
                <Tag key={tag.id}>{tag.name}</Tag>
              ))}
            </Space>
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => showTagModal(record)}
            ></Button>
          </Space>
        );
      },
    },
    {
      title: t('fields.agentName'),
      dataIndex: 'agent',
      render(value: Provider['agent'], record, index) {
        return value ? (
          <ShowButton
            icon={null}
            recordItemId={value.id}
            resourceNameOrRouteName="providers"
          >
            {value.name}
          </ShowButton>
        ) : (
          '无'
        );
      },
    },
    {
      title: t('fields.totalBalance'),
      dataIndex: ['wallet', 'balance'],
      render: (value, record) => {
        return (
          <Space>
            <TextField value={value} />
            <Button
              // disabled={!canEditWallet?.can}
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  title: t('wallet.editTotalBalance'),
                  filterFormItems: [
                    UpdateProviderParams.type,
                    UpdateProviderParams.balance_delta,
                    UpdateProviderParams.note,
                  ],
                  id: record.id,
                });
              }}
            ></Button>
          </Space>
        );
      },
    },
    {
      title: t('fields.availableBalance'),
      dataIndex: ['wallet', 'available_balance'],
    },
    {
      title: t('fields.frozenBalance'),
      dataIndex: ['wallet', 'frozen_balance'],
      render: (value, record) => {
        return (
          <Space>
            <TextField value={value} />
            <Button
              // disabled={!canEditWallet?.can}
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  title: t('actions.adjustFrozenBalance'),
                  filterFormItems: [
                    UpdateProviderParams.type,
                    UpdateProviderParams.frozen_balance_delta,
                    UpdateProviderParams.note,
                  ],
                  id: record.id,
                });
              }}
            ></Button>
          </Space>
        );
      },
    },
    {
      title: t('fields.profit'),
      dataIndex: ['wallet', 'profit'],
      render: (value, record) => {
        return (
          <Space>
            <TextField value={value} />
            <Button
              // disabled={!canEditWallet?.can}
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  title: t('fields.profit'),
                  filterFormItems: [
                    UpdateProviderParams.type,
                    UpdateProviderParams.profit_delta,
                    UpdateProviderParams.note,
                  ],
                  id: record.id,
                });
              }}
            ></Button>
          </Space>
        );
      },
    },
    // {
    //     title: "提现手续费",
    //     dataIndex: "withdraw_fee",
    //     render(value, record, index) {
    //         return (
    //             <Space>
    //                 <TextField value={value} />
    //                 <Button
    //                     // disabled={!canEditWallet?.can}
    //                     icon={<EditOutlined className="text-[#6eb9ff]" />}
    //                     onClick={() => {
    //                         show({
    //                             title: "提现手续费",
    //                             filterFormItems: [UpdateProviderParams.withdraw_fee],
    //                             id: record.id,
    //                         });
    //                     }}
    //                 ></Button>
    //             </Space>
    //         );
    //     },
    // },
    {
      title: t('filters.accountStatus'),
      dataIndex: 'status',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.accountStatus'),
                id: record.id,
                values: {
                  [UpdateProviderParams.status]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: t('fields.google2fa'),
      dataIndex: 'google2fa_enable',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.google2fa'),
                id: record.id,
                values: {
                  [UpdateProviderParams.google2fa_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: t('switches.transactionEnable'),
      dataIndex: 'transaction_enable',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.transactionEnable'),
                id: record.id,
                values: {
                  [UpdateProviderParams.transaction_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: t('switches.depositEnable'),
      dataIndex: 'deposit_enable',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.depositEnable'),
                id: record.id,
                values: {
                  [UpdateProviderParams.deposit_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: t('switches.paufenDepositEnable'),
      dataIndex: 'paufen_deposit_enable',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.paufenDepositEnable'),
                id: record.id,
                values: {
                  [UpdateProviderParams.paufen_deposit_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    {
      title: t('fields.agentEnable'),
      dataIndex: 'agent_enable',
      render(value, record, index) {
        return (
          <Switch
            // disabled={!canEditProfile?.can}
            checked={value}
            onChange={checked => {
              UpdateModal.confirm({
                title: t('confirmation.agentEnable', {
                  agentEnable: t('fields.agentEnable'),
                }),
                id: record.id,
                values: {
                  [UpdateProviderParams.agent_enable]: +checked,
                },
              });
            }}
          />
        );
      },
    },
    // {
    //     title: "提现开关",
    //     dataIndex: "withdraw_enable",
    //     render(value, record, index) {
    //         return (
    //             <Switch
    //                 // disabled={!canEditProfile?.can}
    //                 checked={value}
    //                 onChange={(checked) => {
    //                     UpdateModal.confirm({
    //                         title: "是否修改提现开关",
    //                         id: record.id,
    //                         values: {
    //                             [UpdateProviderParams.withdraw_enable]: +checked,
    //                         },
    //                     });
    //                 }}
    //             />
    //         );
    //     },
    // },
    // {
    //     title: "信用模式",
    //     dataIndex: "transaction_enable",
    //     render(value, record, index) {
    //         return (
    //             <Switch
    //                 // disabled={!canEditProfile?.can}
    //                 checked={value}
    //                 onChange={(checked) => {
    //                     UpdateModal.confirm({
    //                         title: "是否修改信用模式",
    //                         id: record.id,
    //                         values: {
    //                             [UpdateProviderParams.transaction_enable]: +checked,
    //                         },
    //                     });
    //                 }}
    //             />
    //         );
    //     },
    // },
    {
      title: t('fields.lastLoginTime'),
      dataIndex: 'last_login_at',
      render: value =>
        value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : null,
    },
    {
      title: 'IP',
      dataIndex: 'last_login_ipv4',
    },
    {
      title: t('operation', {
        ns: 'common',
      }),
      render(value, record, index) {
        return <DeleteButton recordItemId={record.id} danger></DeleteButton>;
      },
    },
  ];

  const { showTagModal, tagModal } = useTagEdit({
    selectTagProps,
    resource: 'providers',
  });

  return (
    <>
      <Helmet>
        <title>{t('titles.list')}</title>
      </Helmet>
      <List
        title={t('titles.list')}
        headerButtons={() => (
          <>
            <ListButton
              resourceNameOrRouteName="white-list"
              icon={<LoginOutlined />}
            >
              {t('titles.whiteList')}
            </ListButton>
            <ListButton
              resourceNameOrRouteName="wallet-histories"
              icon={<WalletOutlined />}
            >
              {t('titles.balanceHistory')}
            </ListButton>
            <ListButton resourceNameOrRouteName="merchant-transaction-groups">
              {t('titles.moneyInDirectLine')}
            </ListButton>
            <ListButton resourceNameOrRouteName="merchant-matching-deposit-groups">
              {t('titles.moneyOutDirectLine')}
            </ListButton>
            {/* <Button icon={<ApartmentOutlined />}>码商树状图</Button> */}
            <CreateButton icon={<PlusSquareOutlined />}>
              {t('titles.create')}
            </CreateButton>
          </>
        )}
      >
        <Form />
        <Divider />
        <Table {...tableProps} columns={columns} />
        <Modal {...modalProps} />
      </List>
      {tagModal}
    </>
  );
};

export default ProvidersList;
