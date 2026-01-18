import { DeleteOutlined, EditOutlined } from "@ant-design/icons";
import {
    Button,
    DateField,
    Divider,
    Input,
    List,
    Popover,
    Select,
    ShowButton,
    Space,
    TableColumnProps,
} from "@refinedev/antd";
import { useCan } from "@refinedev/core";
import Badge from "components/badge";
import ContentHeader from "components/contentHeader";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { User, UserBankCard } from "interfaces/userBankCard";
import { Format } from "@morgan-ustd/shared";
import Enviroment from "lib/env";
import { FC } from "react";
import { Helmet } from "react-helmet";
import { useTranslation } from "react-i18next";

const UserBankCardList: FC = () => {
    const { t } = useTranslation();
    const isPaufen = Enviroment.isPaufen;
    const title = isPaufen 
        ? t("bankCard.titles.merchantProviderList")
        : t("bankCard.titles.merchantList");
    const { data: canDelete } = useCan({
        action: "14",
        resource: "user-bank-cards",
    });
    const getStatusText = (status: number) => {
        if (status === 1) return t("bankCard.review.wait");
        else if (status === 2) return t("bankCard.review.success");
        else return t("bankCard.review.fail");
    };
    const { Modal } = useUpdateModal();
    const { Form, Table } = useTable<UserBankCard>({
        formItems: [
            {
                label: t("bankCard.fields.nameOrUsername"),
                name: "name_or_username",
                children: <Input />,
            },
            {
                label: t("bankCard.fields.bankCardKeyword"),
                name: "q",
                children: <Input />,
            },
            {
                label: t("bankCard.fields.status"),
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
    const columns: TableColumnProps<UserBankCard>[] = [
        {
            title: t("bankCard.fields.userName"),
            dataIndex: "user",
            render(value: User, record, index) {
                return (
                    <Space>
                        <div className="w-5 h-5 relative">
                            <img
                                src={value.role === 3 ? "/merchant-icon.png" : "/provider-icon.png"}
                                alt=""
                                className="object-contain"
                            />
                        </div>
                        <ShowButton recordItemId={value.id} resourceNameOrRouteName="merchants" icon={null}>
                            {value.name}
                        </ShowButton>
                    </Space>
                );
            },
        },
        {
            title: t("bankCard.fields.cardNumber"),
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
            title: t("bankCard.fields.status"),
            dataIndex: "status",
            render(value: number, record) {
                let text = "";
                let color = "";
                switch (value) {
                    case 1:
                        text = t("bankCard.review.wait");
                        color = "#bebebe";
                        break;
                    case 2:
                        text = t("bankCard.review.success");
                        color = "#16a34a";
                        break;
                    case 3:
                        text = t("bankCard.review.fail");
                        color = "#ff4d4f";
                        break;
                }

                return (
                    <Space>
                        <Badge text={text} color={color} />
                        <Popover
                            trigger={"click"}
                            content={
                                <ul className="popover-edit-list">
                                    {[1, 2, 3]
                                        .filter((x) => x !== value)
                                        .map((status) => (
                                            <li
                                                key={status}
                                                onClick={() => {
                                                    Modal.confirm({
                                                        id: record.id,
                                                        values: {
                                                            status,
                                                        },
                                                        title: t("bankCard.confirmChangeStatus"),
                                                        className: "z-10",
                                                    });
                                                }}
                                            >
                                                {getStatusText(status)}
                                            </li>
                                        ))}
                                </ul>
                            }
                        >
                            <EditOutlined className="text-[#6eb9ff]" />
                        </Popover>
                    </Space>
                );
            },
        },
        {
            title: t("createAt"),
            dataIndex: "created_at",
            render(value: string) {
                return <DateField value={value} format={Format} />;
            },
        },
        {
            title: t("operation"),
            render(value, record) {
                return (
                    <Space>
                        <Button
                            disabled={!canDelete?.can}
                            icon={<DeleteOutlined />}
                            danger
                            type="primary"
                            onClick={() =>
                                Modal.confirm({
                                    title: t("bankCard.confirmDelete", { cardNumber: record.bank_card_number }),
                                    id: record.id,
                                    mode: "delete",
                                })
                            }
                        >
                            {t("delete")}
                        </Button>
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
            <List title={<ContentHeader title={title} resource="withdraws" />}>
                <Form />
                <Divider />
                <Table columns={columns} />
            </List>
            <Modal />
        </>
    );
};

export default UserBankCardList;
