import { EditOutlined } from "@ant-design/icons";
import {
    Button,
    CreateButton,
    Divider,
    Input,
    InputNumber,
    List,
    ListButton,
    Select,
    Space,
    Switch,
    TextField,
} from "@refinedev/antd";
import useProvider from "hooks/useProvider";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { Provider } from "interfaces/provider";
import { FC } from "react";
import { Helmet } from "react-helmet";

const UpdateProviderFormField = {
    balance_delta: "balance_delta",
    note: "note",
    id: "id",
    type: "type",
    frozen_balance_delta: "frozen_balance_delta",
    profit_delta: "profit_delta",
    status: "status",
    withdraw_fee: "withdraw_fee",
    google2fa_enable: "google2fa_enable",
    agent_enable: "agent_enable",
    deposit_enable: "deposit_enable",
    paufen_deposit_enable: "paufen_deposit_enable",
    withdraw_enable: "withdraw_enable",
    transaction_enable: "transaction_enable",
    credit_mode_enable: "credit_mode_enable",
};

const ProviderList: FC = () => {
    const { Select: ProviderSelect } = useProvider();
    const { Form, Table } = useTable<Provider>({
        formItems: [
            {
                label: "群组名称",
                name: "provider_name_or_username[]",
                children: <ProviderSelect mode="multiple" />,
            },
            // {
            //     label: "上级代理名称或登录帐号",
            //     name: "agent_name_or_username",
            //     children: <Input />,
            // },
            // {
            //     label: "帐户启用状态",
            //     name: "status",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "google验证码启用状态",
            //     name: "google2fa_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "代理功能启用状态",
            //     name: "agent_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "充值开关",
            //     name: "deposit_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "交易开关",
            //     name: "transaction_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "销单开关",
            //     name: "cancel_order_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
            // {
            //     label: "站内转点开关",
            //     name: "balance_transfer_enable",
            //     children: <Select {...statusSelectProps} />,
            // },
        ],
        resource: "providers",
    });

    const { show, Modal } = useUpdateModal<Provider>({
        transferFormValues: (values) => {
            if (values.type === "minus") {
                if (values[UpdateProviderFormField.balance_delta]) {
                    values[UpdateProviderFormField.balance_delta] = -values[UpdateProviderFormField.balance_delta];
                }
                if (values[UpdateProviderFormField.frozen_balance_delta]) {
                    values[UpdateProviderFormField.frozen_balance_delta] =
                        -values[UpdateProviderFormField.frozen_balance_delta];
                }
                if (values[UpdateProviderFormField.profit_delta]) {
                    values[UpdateProviderFormField.profit_delta] = -values[UpdateProviderFormField.profit_delta];
                }
            }
            return values;
        },
        formItems: [
            {
                label: "名称",
                name: "name",
                children: <Input />,
            },
            {
                label: "类型",
                name: "type",
                children: (
                    <Select
                        options={["add", "minus"].map((type) => ({
                            label: type === "add" ? "增加" : "减少",
                            value: type,
                        }))}
                    />
                ),
            },
            {
                label: "总余额",
                name: UpdateProviderFormField.balance_delta,
                children: <InputNumber className="w-full" />,
            },
            {
                label: "冻结余额",
                name: UpdateProviderFormField.frozen_balance_delta,
                children: <InputNumber className="w-full" />,
            },
            {
                label: "红利",
                name: UpdateProviderFormField.profit_delta,
                children: <InputNumber className="w-full" />,
            },
            {
                label: "备注",
                name: UpdateProviderFormField.note,
                children: <Input.TextArea />,
            },
            {
                label: "提现手续费",
                name: UpdateProviderFormField.withdraw_fee,
                children: <InputNumber />,
            },
        ],
    });

    return (
        <>
            <Helmet>
                <title>群组管理</title>
            </Helmet>
            <List
                headerButtons={() => (
                    <>
                        <ListButton resourceNameOrRouteName="merchant-transaction-groups">代收专线</ListButton>
                        <ListButton resourceNameOrRouteName="merchant-matching-deposit-groups">代付专线</ListButton>
                        <CreateButton>建立群组</CreateButton>
                    </>
                )}
            >
                <Form />
                <Divider />
                <Table>
                    <Table.Column<Provider>
                        title="群组名称"
                        dataIndex={"name"}
                        render={(value, record) => {
                            return (
                                <Space>
                                    <TextField value={value} />
                                    <EditOutlined
                                        style={{
                                            color: "#6eb9ff",
                                        }}
                                        onClick={() =>
                                            show({
                                                title: "群组名称",
                                                id: record.id,
                                                filterFormItems: ["name"],
                                                initialValues: {
                                                    name: value,
                                                },
                                            })
                                        }
                                    />
                                </Space>
                            );
                        }}
                    />
                    <Table.Column<Provider>
                        title="收款总开关"
                        dataIndex={"transaction_enable"}
                        render={(value, record) => {
                            return (
                                <Switch
                                    checked={value}
                                    onChange={(value) => {
                                        Modal.confirm({
                                            title: "确定要修改收款总开关吗",
                                            id: record.id,
                                            values: {
                                                [UpdateProviderFormField.id]: record.id,
                                                [UpdateProviderFormField.transaction_enable]: +value,
                                            },
                                        });
                                    }}
                                />
                            );
                        }}
                    />
                    <Table.Column<Provider>
                        title="付款总开关"
                        dataIndex={"paufen_deposit_enable"}
                        render={(value, record) => {
                            return (
                                <Switch
                                    checked={value}
                                    onChange={(value) => {
                                        Modal.confirm({
                                            title: "确定要修改付款总开关吗",
                                            id: record.id,
                                            values: {
                                                [UpdateProviderFormField.id]: record.id,
                                                [UpdateProviderFormField.paufen_deposit_enable]: !!value,
                                            },
                                        });
                                    }}
                                />
                            );
                        }}
                    />
                    {/* <Table.Column<Provider>
                        title="提现开关"
                        dataIndex={"withdraw_enable"}
                        render={(value, record) => {
                            return (
                                <Switch
                                    checked={value}
                                    onChange={(value) => {
                                        Modal.confirm({
                                            title: "确定要修改快速充值开关吗",
                                            id: record.id,
                                            values: {
                                                [UpdateProviderFormField.id]: record.id,
                                                [UpdateProviderFormField.withdraw_enable]: +value,
                                            },
                                        });
                                    }}
                                />
                            );
                        }}
                    /> */}
                    {/* <Table.Column<Provider>
                        title="信用模式"
                        dataIndex={"transaction_enable"}
                        render={(value, record) => {
                            return (
                                <Switch
                                    checked={value}
                                    onChange={(value) => {
                                        Modal.confirm({
                                            title: "确定要修改信用模式吗",
                                            id: record.id,
                                            values: {
                                                [UpdateProviderFormField.id]: record.id,
                                                [UpdateProviderFormField.credit_mode_enable]: +value,
                                            },
                                        });
                                    }}
                                />
                            );
                        }}
                    />
                    <Table.Column<Provider>
                        title="最后登录时间"
                        dataIndex={"last_login_at"}
                        render={(value) => {
                            return value ? dayjs(value).format("YYYY-MM-DD HH:mm:ss") : "";
                        }}
                    />
                    <Table.Column<Provider>
                        title="IP"
                        dataIndex={"last_login_ipv4"}
                        render={(value) => {
                            return value;
                        }}
                    /> */}
                    <Table.Column<Provider>
                        title="操作"
                        dataIndex={"id"}
                        render={(id) => {
                            return (
                                <Button
                                    danger
                                    onClick={() =>
                                        Modal.confirm({
                                            title: "确定要删除群组吗？",
                                            id,
                                            mode: "delete",
                                        })
                                    }
                                >
                                    删除
                                </Button>
                            );
                        }}
                    ></Table.Column>
                </Table>
            </List>
            <Modal
                defaultValue={{
                    type: "add",
                    [UpdateProviderFormField.balance_delta]: 0,
                    [UpdateProviderFormField.frozen_balance_delta]: 0,
                    [UpdateProviderFormField.profit_delta]: 0,
                }}
            />
        </>
    );
};

export default ProviderList;
