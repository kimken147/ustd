import {
    Badge,
    Card,
    Col,
    DatePicker,
    Divider,
    Input,
    Radio,
    Row,
    Select,
    Statistic,
} from "antd";
import type { BadgeProps, ColProps, TableColumnProps } from "antd";
import { CreateButton, DateField, ExportButton, List, ListButton } from "@refinedev/antd";
import useTable from "hooks/useTable";
import { Meta, Withdraw } from "interfaces/withdraw";
import { FC } from "react";
import { Helmet } from "react-helmet";
import useWithdrawStatus from "hooks/useWithdrawStatus";
import useTransactionCallbackStatus from "hooks/useTransactionCallbackStatus";
import dayjs from "dayjs";
import useSelector from "hooks/useSelector";
import { Descendant } from "interfaces/descendant";
import { Format } from "@morgan-ustd/shared";
import { PlusSquareOutlined } from "@ant-design/icons";
import CustomDatePicker from "components/customDatePicker";
import { getToken } from "authProvider";
import { apiUrl } from "index";
import queryString from "query-string";
import { generateFilter } from "dataProvider";
import { useGetLocale, useTranslate } from "@refinedev/core";
import { TransactionSubType } from "@morgan-ustd/shared";

const PayForAnotherList: FC = () => {
    const defaultStartAt = dayjs().startOf("days").format();
    const translate = useTranslate();
    const locale = useGetLocale();
    const title = translate("withdraw.titles.main");
    const colProps: ColProps = {
        xs: 24,
        sm: 24,
        md: 4,
    };
    const { Select: DescendantSelect } = useSelector<Descendant>({
        valueField: "username",
        resource: "descendants",
        labelField: "username",
    });
    const {
        Select: WithdrawStatusSelect,
        getStatusText: getWithdrawStatusText,
        Status: WithdrawStatus,
    } = useWithdrawStatus();
    const {
        Select: TranCallbackSelect,
        Status: tranCallbackStatus,
        getStatusText: getTranCallbackStatus,
    } = useTransactionCallbackStatus();
    const { Table, Form, meta, form, filters } = useTable<Withdraw, Meta>({
        formItems: [
            {
                label: translate("datePicker.startDate"),
                name: "started_at",
                trigger: "onSelect",
                children: (
                    <CustomDatePicker
                        showTime
                        className="w-full"
                        onFastSelectorChange={(startAt, endAt) =>
                            form.setFieldsValue({
                                started_at: startAt,
                                ended_at: endAt,
                            })
                        }
                    />
                ),
                rules: [
                    {
                        required: true,
                    },
                ],
            },
            {
                label: translate("datePicker.endDate"),
                name: "ended_at",
                trigger: "onSelect",
                children: <DatePicker showTime className="w-full" />,
            },
            {
                label: translate("collection.fields.transactionNo"),
                name: "order_number_or_system_order_number",
                children: <Input />,
            },
            {
                label: translate("collection.fields.merchantNo"),
                name: "descendant_merchent_username_or_name",
                children: <DescendantSelect mode="multiple" />,
            },
            {
                label: translate("withdraw.fields.bankCardKeyword"),
                name: "bank_card_q",
                children: <Input />,
            },
            {
                label: translate("withdraw.fields.withdrawStatus"),
                name: "status[]",
                children: <WithdrawStatusSelect mode="multiple" />,
            },
            {
                label: translate("withdraw.fields.type"),
                name: "sub_type[]",
                children: (
                    <Select
                        mode="multiple"
                        options={[
                            {
                                label: translate("withdraw.values.withdraw"),
                                value: TransactionSubType.SUB_TYPE_WITHDRAW,
                            },
                            {
                                label: translate("withdraw.values.payout"),
                                value: TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW,
                            },
                        ]}
                    />
                ),
                collapse: true,
            },
            {
                label: translate("collection.fields.callbackStatus"),
                name: "notify_status[]",
                children: <TranCallbackSelect mode="multiple" />,
            },
            {
                label: translate("collection.fields.category"),
                name: "confirmed",
                children: (
                    <Radio.Group>
                        <Radio value={"created"}>{translate("collection.fields.queryOrderWithCreateAt")}</Radio>
                        <Radio value={"confirmed"}>{translate("collection.fields.queryOrderWithSucceedAt")}</Radio>
                    </Radio.Group>
                ),
            },
        ],
        filters: [
            {
                field: "started_at",
                value: defaultStartAt,
                operator: "eq",
            },
            {
                field: "confirmed",
                value: "created",
                operator: "eq",
            },
            {
                field: "lang",
                value: locale(),
                operator: "eq",
            },
        ],
    });

    const columns: TableColumnProps<Withdraw>[] = [
        {
            title: translate("collection.fields.systemTransactionNo"),
            dataIndex: "system_order_number",
        },
        {
            title: translate("collection.fields.merchantTransactionNo"),
            dataIndex: "order_number",
        },
        {
            title: translate("collection.fields.merchantNo"),
            dataIndex: ["merchant", "username"],
        },
        {
            title: translate("withdraw.fields.type"),
            dataIndex: "subType",
            render(value, record, index) {
                return value === 1 ? translate("withdraw.values.withdraw") : translate("withdraw.values.payout");
            },
        },
        {
            title: translate("collection.fields.amount"),
            dataIndex: "amount",
        },
        {
            title: translate("collection.fields.fee"),
            dataIndex: "fee",
        },
        {
            title: translate("withdraw.fields.withdrawStatus"),
            dataIndex: "status",
            render(value, record, index) {
                let status: BadgeProps["status"];
                if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
                    status = "success";
                } else if ([WithdrawStatus.支付超时, WithdrawStatus.匹配超时, WithdrawStatus.失败].includes(value)) {
                    status = "error";
                } else if (
                    [
                        WithdrawStatus.审核中,
                        WithdrawStatus.匹配中,
                        WithdrawStatus.等待付款,
                        WithdrawStatus.三方处理中,
                    ].includes(value)
                ) {
                    status = "processing";
                }
                return <Badge status={status} text={getWithdrawStatusText(value)} />;
            },
        },
        {
            title: translate("withdraw.fields.accountOwner"),
            dataIndex: "bank_card_holder_name",
        },
        {
            title: translate("withdraw.fields.bankName"),
            dataIndex: "bank_name",
        },
        {
            title: translate("withdraw.fields.bankAccount"),
            dataIndex: "bank_card_number",
        },
        {
            title: translate("withdraw.fields.province"),
            dataIndex: "bank_province",
        },
        {
            title: translate("withdraw.fields.city"),
            dataIndex: "bank_city",
        },
        {
            title: translate("createAt"),
            dataIndex: "created_at",
            render(value, record, index) {
                return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
            },
        },
        {
            title: translate("confirmAt"),
            dataIndex: "confirmed_at",
            render(value, record, index) {
                return value ? <DateField value={value} format="YYYY-MM-DD HH:mm:ss" /> : null;
            },
        },

        {
            title: translate("collection.fields.callbackStatus"),
            dataIndex: "notify_status",
            render(value, record, index) {
                let status: BadgeProps["status"];
                if ([tranCallbackStatus.成功].includes(value)) {
                    status = "default";
                } else if ([tranCallbackStatus.通知中, tranCallbackStatus.已通知].includes(value)) {
                    status = "processing";
                } else if (tranCallbackStatus.未通知 === value) {
                    status = "default";
                } else if (tranCallbackStatus.失败) {
                    status = "error";
                }
                return <Badge status={status} text={getTranCallbackStatus(value)} />;
            },
        },
        {
            title: translate("withdraw.fields.callbackTime"),
            dataIndex: "notified_at",
            render(value, record, index) {
                return value ? <DateField value={value} format={Format} /> : "";
            },
        },
    ];

    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <List
                title={title}
                headerButtons={() => (
                    <>
                        <CreateButton resource="withdraws">
                            {translate("withdraw.buttons.createPayment")}
                        </CreateButton>
                        <ListButton icon={<PlusSquareOutlined />} resource="pay-for-another">
                            {translate("withdraw.buttons.createWithdraw")}
                        </ListButton>
                        <ListButton resource="bank-cards">
                            {translate("withdraw.buttons.banks")}
                        </ListButton>
                        <ExportButton
                            onClick={async () => {
                                const url = `${apiUrl}/withdraw-report?${queryString.stringify(
                                    generateFilter(filters),
                                )}&token=${getToken()}`;
                                window.open(url);
                            }}
                        >
                            {translate("export")}
                        </ExportButton>
                    </>
                )}
            >
                <Form
                    initialValues={{
                        started_at: dayjs().startOf("days"),
                        confirmed: "created",
                    }}
                />
                <Divider />
                <Row gutter={16}>
                    <Col {...colProps}>
                        <Card>
                            <Statistic value={meta?.total} title={translate("withdraw.fields.totalNumberOfWithdraw")} />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic
                                value={meta?.total_amount}
                                title={translate("withdraw.fields.totalAmountOfWithdraw")}
                            />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic
                                value={meta?.total_fee}
                                title={translate("withdraw.fields.totalFeeOfWithdraw")}
                            />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic value={meta?.balance} title={translate("withdraw.fields.balanceOfWithdraw")} />
                        </Card>
                    </Col>
                </Row>
                <Divider />
                <Table columns={columns} />
            </List>
        </>
    );
};

export default PayForAnotherList;
