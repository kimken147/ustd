import {
    Badge,
    CreateButton,
    DateField,
    Divider,
    Input,
    List,
    ShowButton,
    Space,
    TableColumnProps,
    TextField,
} from "@pankod/refine-antd";
import { useTranslate } from "@pankod/refine-core";
import Table from "components/table";
import useTable from "hooks/useTable";
import { SubAccount } from "interfaces/subAccount";
import { Format } from "lib/date";
import { FC } from "react";
import { Helmet } from "react-helmet";

const SubAccountList: FC = () => {
    const t = useTranslate();
    const title = t("subAccount.titles.list");
    const columns: TableColumnProps<SubAccount>[] = [
        {
            title: t("subAccount.fields.name"),
            dataIndex: "name",
            render(value, record, index) {
                return (
                    <ShowButton icon={null} recordItemId={record.id}>
                        {value}
                    </ShowButton>
                );
            },
        },
        {
            title: t("subAccount.fields.status"),
            dataIndex: "status",
            render(value, record, index) {
                const color = value === 1 ? "#16a34a" : "#ff4d4f";
                const text = value === 1 ? t("enable") : t("disable");
                return <Badge color={color} text={text} />;
            },
        },
        {
            title: t("subAccount.fields.theLastLoginTimeOrIp"),
            render(value, record, index) {
                return (
                    <Space>
                        {record.last_login_at ? <DateField value={record.last_login_at} format={Format} /> : "-"}
                        /
                        <TextField value={record.last_login_ipv4 || "-"} />
                    </Space>
                );
            },
        },
    ];
    const { Form, tableProps } = useTable({
        resource: "sub-accounts",
        formItems: [
            {
                label: t("subAccount.query.nameOrAccount"),
                name: "name_or_username",
                children: <Input />,
            },
        ],
        columns,
    });
    return (
        <List
            title={title}
            headerButtons={() => (
                <>
                    <CreateButton>{t("subAccount.buttons.create")}</CreateButton>
                </>
            )}
        >
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Form />
            <Divider />
            <Table {...tableProps} />
        </List>
    );
};

export default SubAccountList;
