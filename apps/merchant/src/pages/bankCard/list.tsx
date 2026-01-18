import { DeleteOutlined, EditOutlined } from "@ant-design/icons";
import {
    Badge,
    BadgeProps,
    CreateButton,
    DateField,
    DeleteButton,
    Divider,
    EditButton,
    Input,
    List,
    Select,
    Space,
    TableColumnProps,
    TextField,
} from "@pankod/refine-antd";
import { useTranslate } from "@pankod/refine-core";
import ContentHeader from "components/contentHeader";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { BankCard } from "interfaces/bankCard";
import { Format } from "lib/date";
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
            // {
            //     label: "开户省份",
            //     children: <Input />,
            //     name: "bank_province",
            // },
            // {
            //     label: "开户市",
            //     children: <Input />,
            //     name: "bank_city",
            // },
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
        // {
        //     title: "开户省份",
        //     dataIndex: "bank_province",
        // },
        // {
        //     title: "开户市",
        //     dataIndex: "bank_city",
        // },
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
                            // onClick={() =>
                            //     Modal.confirm({
                            //         title: `是否确定删除卡号:${record.bank_card_number}`,
                            //         id: record.id,
                            //         mode: "delete",
                            //     })
                            // }
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
