import { Descriptions, Input, Show } from "@pankod/refine-antd";
import { useShow } from "@pankod/refine-core";
import EditableForm from "components/EditableFormItem";
import { SystemBankCard } from "interfaces/systemBankCard";
import { FC } from "react";
import { Helmet } from "react-helmet";

const SystemBankCardShow: FC = () => {
    const title = "系統銀行卡詳情";
    const { queryResult } = useShow<SystemBankCard>();
    const { data, isLoading } = queryResult;
    const record = data?.data;
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Show title={title} headerButtons={<></>} isLoading={isLoading}>
                <Descriptions bordered size="small" column={{ xs: 1, md: 2, lg: 3 }}>
                    <Descriptions.Item label="卡號">
                        <EditableForm id={record?.id || 0} name="name">
                            <Input defaultValue={record?.bank_card_number} className="w-full !text-stone-500" />
                        </EditableForm>
                    </Descriptions.Item>
                    <Descriptions.Item label="持卡人名稱">
                        <EditableForm id={record?.id || 0} name="name">
                            <Input defaultValue={record?.bank_card_number} className="w-full !text-stone-500" />
                        </EditableForm>
                    </Descriptions.Item>
                </Descriptions>
            </Show>
        </>
    );
};

export default SystemBankCardShow;
