import {
  CreateButton,
  DeleteButton,
  EditButton,
  List,
} from '@refinedev/antd';
import {
  Input,
  InputNumber,
  Modal,
  Radio,
  Space,
  Table,
} from 'antd';
import { useList } from "@refinedev/core";
import ContentHeader from "components/contentHeader";
import useUpdateModal from "hooks/useUpdateModal";
import { Detail, TransactionReward } from "interfaces/transactionReward";
import { FC } from "react";
import { Helmet } from "react-helmet";

const TransactionRewardList: FC = () => {
    const title = "交易奖励";
    const { data, isLoading } = useList({
        resource: "transaction-rewards",
        config: {
            hasPagination: false,
        },
    });
    const rewards = Object.entries((data?.data as unknown as TransactionReward) ?? []).reduce<Detail[]>((prev, cur) => {
        cur[1].forEach((item) => (item.timeRange = cur[0]));
        return [...prev, ...cur[1]];
    }, []);

    const { modalProps, show } = useUpdateModal({
        formItems: [
            {
                label: "金额区间（格式 : 100~200)",
                name: "amount",
                children: <Input />,
            },
            {
                label: "模式",
                name: "reward_unit",
                children: (
                    <Radio.Group>
                        <Radio value={1}>单笔奖励</Radio>
                        <Radio value={2}>% 奖励</Radio>
                    </Radio.Group>
                ),
            },
            {
                label: "奖励佣金",
                name: "reward_amount",
                children: <InputNumber className="w-full" />,
            },
        ],
        transferFormValues(record) {
            delete record.started_at;
            delete record.ended_at;
            return record;
        },
    });

    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <List
                title={<ContentHeader title={title} resource="transactions" />}
                // headerButtons={
                //     <>
                //         <CreateButton>建立交易奖励</CreateButton>
                //     </>
                // }
            >
                <Table
                    loading={isLoading}
                    dataSource={rewards}
                    pagination={false}
                    columns={[
                        {
                            title: "奖励时段",
                            dataIndex: "timeRange",
                            onCell: (data) => {
                                const collection = rewards.filter((item) => item.timeRange === data.timeRange);
                                const index = collection.findIndex((item) => item.id === data.id);
                                if (collection.length) {
                                    if (index !== 0) {
                                        return {
                                            rowSpan: 0,
                                        };
                                    }
                                    return {
                                        rowSpan: collection.length,
                                    };
                                }
                                return {};
                            },
                        },
                        {
                            title: "金额区间",
                            render(value, record, index) {
                                return `${record.min_amount}~${record.max_amount}`;
                            },
                        },
                        {
                            title: "奖励佣金",
                            render(value, record, index) {
                                return `${record.reward_amount}/${record.reward_unit === 1 ? "笔" : "%"}`;
                            },
                        },
                        {
                            title: "操作",
                            render(value, record, index) {
                                return (
                                    <Space>
                                        <EditButton
                                            onClick={() =>
                                                show({
                                                    title: "修改交易奖励",
                                                    initialValues: {
                                                        ...record,
                                                        amount: `${record.min_amount}~${record.max_amount}`,
                                                    },
                                                    id: record.id,
                                                })
                                            }
                                        >
                                            修改
                                        </EditButton>
                                        <DeleteButton
                                            confirmCancelText="取消"
                                            confirmOkText="确定"
                                            confirmTitle="确定要删除吗"
                                            recordItemId={record.id}
                                            successNotification={{
                                                message: "刪除成功",
                                                type: "success",
                                            }}
                                        >
                                            删除
                                        </DeleteButton>
                                    </Space>
                                );
                            },
                        },
                    ]}
                />
            </List>
            <Modal {...modalProps} />
        </>
    );
};

export default TransactionRewardList;
