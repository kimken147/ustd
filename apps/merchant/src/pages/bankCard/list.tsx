import { DeleteOutlined, EditOutlined } from "@ant-design/icons";
import { Badge, Divider, Input, Select, Space } from "antd";
import type { BadgeProps, TableColumnProps } from "antd";
import {
    CreateButton,
    DateField,
    DeleteButton,
    EditButton,
    List,
    TextField,
} from "@refinedev/antd";
import { useTranslate } from "@refinedev/core";
import ContentHeader from "components/contentHeader";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { BankCard } from "interfaces/bankCard";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const BankCardList: FC = () => {
    const t = useTranslate();
    const title = t("bankCard.titles.list");
    const { Modal, show: showUpdateModal } = useUpdateModal({
        formItems: [
            {
                label: t("bankCard.fields.accountOwner"),
                name: "bank_card_holder_name",
                children: <Input />,
                rules: [{ required: true }],
            },
            {
                label: t("bankCard.fields.bankAccount"),
                name: "bank_card_number",
                children: <Input />,
                rules: [{ required: true }],
            },
            {
                label: t("bankCard.fields.bankName"),
                name: "bank_name",
                children: <Input />,
                rules: [{ required: true }],
            },
        ],
    });
    const { Form, Table } = useTable({
        formItems: [
            {
                label: t("bankCard.fields.bankAccount"),
                name: "q",
                children: <Input />,
            },
            {
                label: t("status"),
                name: "status[]",
                children: (
                    <Select
                        mode="multiple"
                        options={[
                            {
                                label: t("bankCard.review.wait"),
                                value: 1,
                            },
                            {
                                label: t("bankCard.review.success"),
                                value: 2,
                            },
                            {
                                label: t("bankCard.review.fail"),
                                value: 3,
                            },
                        ]}
                    />
                ),
            },
        ],
    });
    const columns: TableColumnProps<BankCard>[] = [
        {
            title: t("bankCard.fields.bankAccount"),
            dataIndex: "bank_card_number",
        },
        {
            title: t("bankCard.fields.accountOwner"),
            dataIndex: "bank_card_holder_name",
        },
        {
            title: t("bankCard.fields.bankName"),
            dataIndex: "bank_name",
        },
        {
            title: t("status"),
            dataIndex: "status",
            render(value) {
                let text = "";
                let status: BadgeProps["status"] = "default";
                switch (value) {
                    case 1:
                        text = t("bankCard.review.wait");
                        break;
                    case 2:
                        text = t("bankCard.review.success");
                        status = "success";
                        break;
                    case 3:
                        text = t("bankCard.review.fail");
                        status = "error";
                        break;
                }

                return (
                    <Space>
                        <Badge status={status} />
                        <TextField value={text} />
                    </Space>
                );
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
            render(record) {
                return (
                    <Space>
                        <EditButton
                            onClick={() => {
                                showUpdateModal({
                                    initialValues: record,
                                    id: record.id,
                                    filterFormItems: [],
                                    title: t("bankCard.fields.edit"),
                                });
                            }}
                            icon={<EditOutlined />}
                        >
                            {t("edit")}
                        </EditButton>
                        <DeleteButton
                            recordItemId={record.id}
                            icon={<DeleteOutlined />}
                            danger
                            successNotification={{
                                type: "success",
                                message: t("success"),
                            }}
                        >
                            {t("delete")}
                        </DeleteButton>
                    </Space>
                );
            },
        },
    ];
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <List
                title={<ContentHeader title={title} />}
                headerButtons={() => (
                    <>
                        <CreateButton>{t("bankCard.buttons.create")}</CreateButton>
                    </>
                )}
            >
                <Form />
                <Divider />
                <Table columns={columns} />
            </List>
            <Modal />
        </>
    );
};

export default BankCardList;
