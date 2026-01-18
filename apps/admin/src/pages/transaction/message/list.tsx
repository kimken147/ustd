import { DateField, DatePicker, Divider, Input, List, TableColumnProps } from "@pankod/refine-antd";
import CustomDatePicker from "components/customDatePicker";
import dayjs, { Dayjs } from "dayjs";
import useAutoRefetch from "hooks/useAutoRefetch";
import useTable from "hooks/useTable";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const TransactionMessageList: FC = () => {
    const { freq, enableAuto, AutoRefetch } = useAutoRefetch();
    const { form, Table, Form } = useTable({
        formItems: [
            {
                label: "开始日期",
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
                label: "结束日期",
                name: "ended_at",
                trigger: "onSelect",
                children: (
                    <DatePicker
                        showTime
                        className="w-full"
                        disabledDate={(current) => {
                            const startAt = form.getFieldValue("started_at") as Dayjs;
                            return current && (current > startAt.add(1, "month") || current < startAt);
                        }}
                    />
                ),
            },
            {
                label: "短信账号",
                name: "mobile",
                children: <Input />,
            },
            {
                label: "内容",
                name: "content",
                children: <Input />,
            },
        ],
        queryOptions: {
            refetchInterval: enableAuto ? freq * 1000 : undefined,
        },
    });

    const columns: TableColumnProps<Notification>[] = [
        {
            title: "建立时间",
            dataIndex: "created_at",
            render(value, record, index) {
                return value ? <DateField value={value} format={Format} /> : null;
            },
        },
        {
            title: "短信账号",
            dataIndex: "mobile",
        },
        {
            title: "短信内容",
            dataIndex: "content",
        },
    ];

    return (
        <List>
            <Helmet>
                <title>短信</title>
            </Helmet>
            <Form
                initialValues={{
                    started_at: dayjs().startOf("days"),
                }}
            />
            <Divider />
            <AutoRefetch />
            <Table columns={columns} />
        </List>
    );
};

export default TransactionMessageList;
