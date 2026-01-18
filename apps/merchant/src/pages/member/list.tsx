import { Divider, Input, Space } from "antd";
import type { TableColumnProps } from "antd";
import { CreateButton, DateField, List, ShowButton, TextField } from "@refinedev/antd";
import useTable from "hooks/useTable";
import { Member } from "interfaces/member";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberList: FC = () => {
    const { Form, Table } = useTable<Member>({
        formItems: [
            {
                label: "商户名称",
                name: "name_or_username",
                children: <Input />,
            },
        ],
    });
    const columns: TableColumnProps<Member>[] = [
        {
            title: "商户名称",
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
            title: "登录帐号",
            dataIndex: "username",
        },
        {
            title: "总余额",
            dataIndex: ["wallet", "balance"],
        },
        {
            title: "冻结余额",
            dataIndex: ["wallet", "frozen_balance"],
        },
        {
            title: "可用余额",
            dataIndex: ["wallet", "available_balance"],
        },
        {
            title: "最后登录时间 / IP",
            render(_, record, index) {
                if (!record.last_login_at) return "尚无纪录";
                return (
                    <Space>
                        <DateField value={record.last_login_at} format={Format} />
                        <TextField value={record.last_login_ipv4} />
                    </Space>
                );
            },
        },
    ];
    return (
        <>
            <Helmet>
                <title>下级管理</title>
            </Helmet>
            <List
                title="下级管理"
                headerButtons={() => (
                    <>
                        <CreateButton>建立下级帐号</CreateButton>
                    </>
                )}
            >
                <Form />
                <Divider />
                <Table columns={columns} />
            </List>
        </>
    );
};

export default MemberList;
