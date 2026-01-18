import {
    Descriptions,
    ListButton,
    RefreshButton,
    Show,
    Spin,
    Table,
    TableColumnProps,
    TextField,
    Typography,
} from "@pankod/refine-antd";
import { useShow, useUpdate } from "@pankod/refine-core";
import { MerchantFee, Transaction } from "interfaces/transaction";
import { ProviderFee } from "interfaces/withdraw";
import Enviroment from "lib/env";
import { FC } from "react";
import { Helmet } from "react-helmet";

const CollectionShow: FC = () => {
    const isPaufen = Enviroment.isPaufen;
    const { queryResult } = useShow<Transaction>();
    const { mutateAsync } = useUpdate();
    if (!queryResult.data) return <Spin />;
    const {
        data: { data },
        refetch,
    } = queryResult;

    const columns: TableColumnProps<MerchantFee>[] = [
        {
            title: "商户名称",
            dataIndex: ["merchant", "name"],
        },
        {
            title: "手续费",
            dataIndex: "fee",
        },
        {
            title: "利润",
            dataIndex: "profit",
        },
    ];

    const providerColumns: TableColumnProps<ProviderFee>[] = [
        {
            title: "码商名称",
            dataIndex: ["provider", "name"],
        },
        {
            title: "手续费",
            dataIndex: "fee",
        },
        {
            title: "利润",
            dataIndex: "profit",
        },
    ];
    return (
        <>
            <Helmet>
                <title>代收资讯</title>
            </Helmet>
            <Show
                title="代收资讯"
                headerButtons={() => (
                    <>
                        <ListButton>代收列表</ListButton>
                        <RefreshButton>刷新</RefreshButton>
                    </>
                )}
            >
                <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered>
                    <Descriptions.Item label={(isPaufen ? "码商" : "群组") + "名称"}>
                        {data.provider?.name ?? "无"}
                    </Descriptions.Item>
                    <Descriptions.Item label="实付金额">{data.actual_amount}</Descriptions.Item>
                    <Descriptions.Item label="浮动金额">{data.floating_amount}</Descriptions.Item>
                    <Descriptions.Item label="系统利润">{data.system_profit}</Descriptions.Item>
                    <Descriptions.Item label="备注">
                        <TextField
                            editable={{
                                onChange: async (value) => {
                                    await mutateAsync({
                                        id: data.id,
                                        values: {
                                            note: value,
                                            id: data.id,
                                        },
                                        resource: "transactions",
                                        successNotification: {
                                            message: "更新备注成功",
                                            type: "success",
                                        },
                                    });
                                    refetch();
                                },
                            }}
                            value={data.note}
                        />
                    </Descriptions.Item>
                </Descriptions>
                <Typography.Title level={5} className="mt-4">
                    商户及商户代理手续费、利润列表
                </Typography.Title>
                <Table
                    dataSource={data.merchant_fees}
                    rowKey={(record: MerchantFee) => record.merchant.id}
                    columns={columns}
                    pagination={false}
                ></Table>
                {isPaufen && (
                    <>
                        <Typography.Title level={5} className="mt-4">
                            码商及码商代理手续费、利润列表
                        </Typography.Title>
                        <Table
                            dataSource={data.provider_fees}
                            rowKey={(record: ProviderFee) => record.provider.id}
                            columns={providerColumns}
                            pagination={false}
                        ></Table>
                    </>
                )}
            </Show>
        </>
    );
};

export default CollectionShow;
