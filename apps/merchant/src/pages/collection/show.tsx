import { Descriptions, Show, Spin, Table, TableColumnProps, Typography } from "@pankod/refine-antd";
import { useShow, useTranslate } from "@pankod/refine-core";
import { MerchantFee, Transaction } from "interfaces/transaction";
import { FC } from "react";
import { Helmet } from "react-helmet";

const CollectionShow: FC = () => {
    const t = useTranslate();
    const title = t("collection.titles.info");
    const { queryResult } = useShow<Transaction>();
    if (!queryResult.data) return <Spin />;
    const {
        data: { data },
    } = queryResult;

    const columns: TableColumnProps<MerchantFee>[] = [
        {
            title: t("collection.fields.merchantName"),
            dataIndex: ["merchant", "name"],
        },
        {
            title: t("fee"),
            dataIndex: "fee",
        },
        {
            title: t("profit"),
            dataIndex: "profit",
        },
    ];
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Show title={title} headerButtons={() => <></>}>
                <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered>
                    <Descriptions.Item label={t("collection.fields.amountPaid")}>
                        {data.actual_amount}
                    </Descriptions.Item>
                    <Descriptions.Item label={t("collection.fields.floatingAmount")}>
                        {data.floating_amount}
                    </Descriptions.Item>
                </Descriptions>
                <Typography.Title level={5} className="mt-4">
                    {t("collection.fields.merchantAndAgentFeeProfitList")}
                </Typography.Title>
                <Table
                    dataSource={data.merchant_fees}
                    rowKey={(record: MerchantFee) => record.merchant.id}
                    columns={columns}
                    pagination={false}
                ></Table>
            </Show>
        </>
    );
};

export default CollectionShow;
