import {
    Badge,
    Card,
    Col,
    DatePicker,
    Divider,
    Input,
    Radio,
    Row,
    Statistic,
} from "antd";
import type { BadgeProps, ColProps, TableColumnProps } from "antd";
import { DateField, ExportButton, List, ShowButton } from "@refinedev/antd";
import dayjs from "dayjs";
import useTable from "hooks/useTable";
import { FC } from "react";
import { Helmet } from "react-helmet";
import useTransactionStatus from "hooks/useTransactionStatus";
import useTransactionCallbackStatus from "hooks/useTransactionCallbackStatus";
import { Meta, Transaction } from "interfaces/transaction";
import numeral from "numeral";
import { Format, Channel } from "@morgan-ustd/shared";
import useSelector from "hooks/useSelector";
import { Descendant } from "interfaces/descendant";
import CustomDatePicker from "components/customDatePicker";
import { useApiUrl, useGetLocale, useTranslate } from "@refinedev/core";
import queryString from "query-string";
import { generateFilter } from "dataProvider";
import { getToken } from "authProvider";

const CollectionList: FC = () => {
    const t = useTranslate();
    const locale = useGetLocale();
    const title = t("collection.titles.list");
    const defaultStartAt = dayjs().startOf("days").format();

    const apiUrl = useApiUrl();
    const { Select: ChannelSelect } = useSelector<Channel>({
        resource: "channels",
        valueField: "code",
        labelRender: (record) => {
            return t(`channels.${record.code}`);
        },
    });
    const { Select: DescendantSelect } = useSelector<Descendant>({
        valueField: "username",
        resource: "descendants",
        labelField: "username",
    });
    const { Select: TransStatSelect, Status: tranStatus, getStatusText: getTranStatusText } = useTransactionStatus();

    const {
        Select: TranCallbackSelect,
        Status: tranCallbackStatus,
        getStatusText: getTranCallbackStatus,
    } = useTransactionCallbackStatus();

    const { Form, Table, meta, form, filters } = useTable<Transaction, Meta>({
        resource: "transactions",
        formItems: [
            {
                label: t("datePicker.startDate"),
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
                label: t("datePicker.endDate"),
                name: "ended_at",
                trigger: "onSelect",
                children: <DatePicker showTime className="w-full" />,
            },
            {
                label: t("collection.fields.transactionNo"),
                name: "order_number_or_system_order_number",
                children: <Input allowClear />,
            },
            {
                label: t("collection.fields.merchantNo"),
                name: "descendant_merchent_username_or_name",
                children: <DescendantSelect mode="multiple" />,
            },
            {
                label: t("collection.fields.realName"),
                name: "real_name",
                children: <Input />
            },
            {
                label: t("collection.fields.channels"),
                name: "channel_code[]",
                children: <ChannelSelect mode="multiple" />,
            },
            {
                label: t("collection.fields.amount"),
                name: "amount",
                children: <Input />,
            },
            {
                label: t("collection.fields.transactionStatus"),
                name: "status[]",
                children: <TransStatSelect mode="multiple" />,
            },
            {
                label: t("collection.fields.callbackStatus"),
                name: "notify_status[]",
                children: <TranCallbackSelect mode="multiple" />,
            },
            {
                label: t("collection.fields.category"),
                name: "confirmed",
                children: (
                    <Radio.Group>
                        <Radio value={"created"}>{t("collection.fields.queryOrderWithCreateAt")}</Radio>
                        <Radio value={"confirmed"}>{t("collection.fields.queryOrderWithSucceedAt")}</Radio>
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

    const columns: TableColumnProps<Transaction>[] = [
        {
            title: t("collection.fields.systemTransactionNo"),
            dataIndex: "system_order_number",
            render(value, record) {
                return value;
            },
        },
        {
            title: t("collection.fields.merchantTransactionNo"),
            dataIndex: "order_number",
        },
        {
            title: t("collection.fields.merchantNo"),
            dataIndex: ["merchant", "username"],
        },
        {
            title: t("collection.fields.channels"),
            dataIndex: "channel_code",
            render(value, record, index) {
                return t(`channels.${value}`);
            },
        },
        {
            title: t("collection.fields.amount"),
            dataIndex: "amount",
        },
        {
            title: t("collection.fields.transactionStatus"),
            dataIndex: "status",
            render(value): JSX.Element {
                let status: BadgeProps["status"];
                if ([tranStatus.成功, tranStatus.手动成功].includes(value)) {
                    status = "success";
                } else if ([tranStatus.付款超时, tranStatus.匹配超时, tranStatus.失败].includes(value)) {
                    status = "error";
                } else if (
                    [tranStatus.已建立, tranStatus.匹配中, tranStatus.等待付款, tranStatus.三方处理中].includes(value)
                ) {
                    status = "processing";
                }
                return <Badge status={status} text={getTranStatusText(value)} />;
            },
        },
        {
            title: t("collection.fields.fee"),
            dataIndex: "fee",
        },
        {
            title: t("collection.fields.realName"),
            dataIndex: "real_name"
        },
        {
            title: t("collection.fields.callbackStatus"),
            dataIndex: "notify_status",
            render(value) {
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
            title: t("createAt"),
            dataIndex: "created_at",
            render(value) {
                return <DateField value={value} format={Format} />;
            },
        },
        {
            title: t("confirmAt"),
            dataIndex: "confirmed_at",
            render(value) {
                return value ? <DateField value={value} format={Format} /> : null;
            },
        },
    ];
    const colProps: ColProps = {
        xs: 24,
        sm: 24,
        md: 12,
        lg: 6,
    };
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <List
                title={title}
                headerButtons={
                    <>
                        <ExportButton
                            onClick={async () => {
                                const url = `${apiUrl}/transaction-report?${queryString.stringify(
                                    generateFilter(filters),
                                )}&token=${getToken()}`;
                                window.open(url);
                            }}
                        >
                            {t("export")}
                        </ExportButton>
                    </>
                }
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
                            <Statistic value={meta?.total} title={t("collection.fields.totalNumberOfTransation")} />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic
                                value={`${meta?.total_success ?? 0}/${meta?.total ?? 0}`}
                                title={`${t("collection.fields.successRate")} ${`${numeral(
                                    ((+meta?.total_success || 0) * 100) / (meta?.total ?? 0),
                                ).format("0.00")}`}%`}
                            />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic
                                value={meta?.total_amount}
                                title={t("collection.fields.totalAmountOfTransaction")}
                            />
                        </Card>
                    </Col>
                    <Col {...colProps}>
                        <Card>
                            <Statistic value={meta?.total_fee} title={t("collection.fields.totalFeeOfTranaction")} />
                        </Card>
                    </Col>
                </Row>
                <Divider />
                <Table columns={columns} />
            </List>
        </>
    );
};

export default CollectionList;
