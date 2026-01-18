import {
    Card,
    Col,
    DateField,
    DatePicker,
    Descriptions,
    Divider,
    ExportButton,
    Input,
    List,
    Row,
    Select,
    Statistic,
    TableColumnProps,
    TextField,
} from "@pankod/refine-antd";
import { Option, useApiUrl, useGetLocale, useTranslate } from "@pankod/refine-core";
import { getToken } from "authProvider";
import CustomDatePicker from "components/customDatePicker";
import { generateFilter } from "dataProvider";
import dayjs from "dayjs";
import useProfile from "hooks/useProfile";
import useStatus from "hooks/useStatus";
import useTable from "hooks/useTable";
import { Meta, WalletHistory } from "interfaces/wallet-history";
import { Format } from "@morgan-ustd/shared";
import numeral from "numeral";
import queryString from "query-string";
import { FC } from "react";
import { Helmet } from "react-helmet";

const WalletHistoryList: FC = () => {
    const t = useTranslate();
    const locale = useGetLocale();
    const title = t("walletHistory.titles.list");
    const apiUrl = useApiUrl();
    const defaultStartAt = dayjs().startOf("days");
    const { data: profile } = useProfile();
    const Status = {
        系统调整: 1,
        余额转赠: 2,
        入帐: 3,
        预扣: 4,
        预扣退款: 5,
        快充奖励: 6,
        交易奖励: 7,
        失败: 8,
        "系统调整(冻结)": 11,
        提现: 12,
        提现退款: 13,
        入帐退款: 14,
    };

    const { Select: WalletHistoryStatusSelect } = useStatus({ status: Status });
    const { Form, Table, meta, form, filters } = useTable<WalletHistory, Meta>({
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
                label: t("walletHistory.fields.alterationCategories"),
                name: "type[]",
                children: (
                    <Select
                        mode="multiple"
                        options={Object.values(Status).map<Option>((value) => ({
                            label: t(`walletHistory.status.${value}`),
                            value: value.toString(),
                        }))}
                    />
                ),
            },
            {
                label: t("note"),
                name: "note",
                children: <Input />,
            },
        ],
        filters: [
            {
                field: "lang",
                value: locale(),
                operator: "eq",
            },
        ],
    });
    const columns: TableColumnProps<WalletHistory>[] = [
        {
            title: t("walletHistory.fields.alterationCategories"),
            dataIndex: "type",
            render(value, record, index) {
                return <TextField value={t(`walletHistory.status.${value}`)} />;
            },
        },
        {
            title: t("walletHistory.fields.totalBalanceAlteration"),
            dataIndex: "balance_delta",
            render(value, record, index) {
                const amount = numeral(value).value() ?? 1;
                return <TextField value={value} className={amount > 0 ? "text-blue-500" : "text-red-500"} />;
            },
        },
        {
            title: t("walletHistory.fields.frozenBalanceAlteration"),
            dataIndex: "frozen_balance_delta",
        },
        {
            title: t("walletHistory.fields.totalBalanceAfterAlteration"),
            dataIndex: "balance_result",
        },
        {
            title: t("walletHistory.fields.frozenBalanceAfterAlteration"),
            dataIndex: "frozen_balance_result",
        },
        {
            title: t("note"),
            dataIndex: "note",
        },
        {
            title: t("walletHistory.fields.alterationTime"),
            dataIndex: "created_at",
            render(value, record, index) {
                return <DateField value={value} format={Format} />;
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
                        <ExportButton
                            onClick={async () => {
                                const url = `${apiUrl}/wallet-histories-report?${queryString.stringify(
                                    generateFilter(filters),
                                )}&token=${getToken()}`;
                                window.open(url);
                            }}
                        >
                            {t("export")}
                        </ExportButton>
                    </>
                )}
            >
                <Form
                    initialValues={{
                        started_at: defaultStartAt,
                    }}
                />
                <Divider />
                <Card>
                    <Descriptions column={{ xs: 1, md: 3 }} bordered title={t("home.fields.balance")}>
                        <Descriptions.Item label={t("home.fields.balance")}>
                            {profile?.wallet.balance}
                        </Descriptions.Item>
                        <Descriptions.Item label={t("home.fields.availableBalance")}>
                            {profile?.wallet.available_balance}
                        </Descriptions.Item>
                        <Descriptions.Item label={t("home.fields.frozenBalance")}>
                            {profile?.wallet.frozen_balance}
                        </Descriptions.Item>
                    </Descriptions>
                </Card>
                <Divider />
                <Row gutter={16}>
                    <Col xs={24} md={6}>
                        <Card>
                            <Statistic
                                title={t("walletHistory.fields.totalAmount")}
                                value={meta?.wallet_balance_total || 0}
                            />
                        </Card>
                    </Col>
                </Row>
                <Divider />
                <Table columns={columns} />
            </List>
        </>
    );
};

export default WalletHistoryList;
