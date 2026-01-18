import {
  ListButton,
  RefreshButton,
  Show,
  TextField,
} from '@refinedev/antd';
import {
  Descriptions,
  Spin,
  Table,
  TableColumnProps,
  Typography,
} from 'antd';
import { useShow, useUpdate } from "@refinedev/core";
import { MerchantFee, Withdraw } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const PayForAnotherShow: FC = () => {
    const { queryResult } = useShow<Withdraw>();
    const { mutateAsync } = useUpdate();
    if (!queryResult.data) return <Spin />;
    const {
        data: { data },
        refetch,
    } = queryResult;

    const columns: TableColumnProps<MerchantFee>[] = [
        {
            title: "商戶名稱",
            dataIndex: ["merchant", "name"],
        },
        {
            title: "手續費",
            dataIndex: "fee",
        },
        {
            title: "利潤",
            dataIndex: "profit",
        },
    ];
    return (
        <>
            <Helmet>
                <title>代付資訊</title>
            </Helmet>
            <Show
                title="代付資訊"
                headerButtons={() => (
                    <>
                        <ListButton>代付列表</ListButton>
                        <RefreshButton>刷新</RefreshButton>
                    </>
                )}
            >
                <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered>
                    <Descriptions.Item label="群組名稱">{data.provider?.name ?? "无"}</Descriptions.Item>
                    <Descriptions.Item label="實付金額">{data.actual_amount}</Descriptions.Item>
                    <Descriptions.Item label="浮动金额">{data.floating_amount}</Descriptions.Item>
                    <Descriptions.Item label="系統利潤">{data.system_profit}</Descriptions.Item>
                    <Descriptions.Item label="備註">
                        <TextField
                            editable={{
                                onChange: async (value) => {
                                    await mutateAsync({
                                        id: data.id,
                                        values: {
                                            note: value,
                                            id: data.id,
                                        },
                                        resource: "withdraws",
                                        successNotification: {
                                            message: "更新備註成功",
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
            </Show>
        </>
    );
};

export default PayForAnotherShow;
