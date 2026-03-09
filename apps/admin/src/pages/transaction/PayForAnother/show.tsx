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
import {
  MerchantFee,
  Withdraw,
  ProviderFee,
  useWithdrawStatus,
  useTransactionCallbackStatus,
} from "@morgan-ustd/shared";
import { useAppMode, useTerminology } from "contexts/AppModeContext";
import { FC } from "react";
import { Helmet } from "react-helmet";
import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';
import numeral from 'numeral';

const TRONSCAN_BASE = 'https://tronscan.org/#/transaction/';

const PayForAnotherShow: FC = () => {
    const { isPaufen } = useAppMode();
    const { t } = useTranslation('transaction');
    const { query } = useShow<Withdraw>();
    const { mutateAsync } = useUpdate();
    const { getStatusText: getWithdrawStatusText } = useWithdrawStatus();
    const { getStatusText: getCallbackStatusText } = useTransactionCallbackStatus();

    if (!query.data) return <Spin />;
    const {
        data: { data },
        refetch,
    } = query;

    const columns: TableColumnProps<MerchantFee>[] = [
        {
            title: t('fields.merchantName'),
            dataIndex: ["merchant", "name"],
        },
        {
            title: t('fields.fee'),
            dataIndex: "fee",
        },
        {
            title: t('fields.profit'),
            dataIndex: "profit",
        },
    ];

    const providerColumns: TableColumnProps<ProviderFee>[] = [
        {
            title: "码商名称",
            dataIndex: ["provider", "name"],
        },
        {
            title: t('fields.fee'),
            dataIndex: "fee",
        },
        {
            title: t('fields.profit'),
            dataIndex: "profit",
        },
    ];

    const { groupLabel } = useTerminology();

    return (
        <>
            <Helmet>
                <title>{t('withdraw.info')}</title>
            </Helmet>
            <Show
                title={t('withdraw.info')}
                headerButtons={() => (
                    <>
                        <ListButton>{t('withdraw.list')}</ListButton>
                        <RefreshButton>{t('actions.refresh')}</RefreshButton>
                    </>
                )}
            >
                <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered size="small">
                    <Descriptions.Item label={t('fields.systemOrderNumber')}>
                        {data.system_order_number}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.merchantOrderNumber')}>
                        {data.order_number}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.status')}>
                        {getWithdrawStatusText(data.status)}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.callbackStatus')}>
                        {getCallbackStatusText(data.notify_status)}
                    </Descriptions.Item>
                    {data.chain_network && (
                        <Descriptions.Item label={t('fields.chainNetwork')}>
                            {data.chain_network}
                        </Descriptions.Item>
                    )}
                    {data.tx_hash && (
                        <Descriptions.Item label="TX Hash">
                            <Typography.Text copyable={{ text: data.tx_hash }}>
                                <a href={`${TRONSCAN_BASE}${data.tx_hash}`} target="_blank" rel="noopener noreferrer">
                                    {data.tx_hash.slice(0, 10)}...{data.tx_hash.slice(-6)}
                                </a>
                            </Typography.Text>
                        </Descriptions.Item>
                    )}
                    <Descriptions.Item label={t('fields.merchantName')}>
                        {data.user?.name ?? '-'}
                    </Descriptions.Item>
                    {data.provider && (
                        <Descriptions.Item label={groupLabel + '名称'}>
                            {data.provider.name}
                        </Descriptions.Item>
                    )}
                    {data.thirdchannel && (
                        <Descriptions.Item label={t('fields.thirdPartyName')}>
                            {data.thirdchannel.name}
                        </Descriptions.Item>
                    )}
                    {data.to_channel_account_hash_id && (
                        <Descriptions.Item label={t('fields.accountNumber')}>
                            {data.to_channel_account_hash_id}
                        </Descriptions.Item>
                    )}
                    <Descriptions.Item label={t('fields.amount')}>
                        {numeral(data.amount).format('0,0.00')}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.actualAmount')}>
                        {numeral(data.actual_amount).format('0,0.00')}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.floatingAmount')}>
                        {numeral(data.floating_amount).format('0,0.00')}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.systemProfit')}>
                        {data.system_profit ?? '-'}
                    </Descriptions.Item>
                    {data.bank_name && (
                        <Descriptions.Item label={t('fields.bankName')}>
                            {data.bank_name}
                        </Descriptions.Item>
                    )}
                    {data.bank_card_number && (
                        <Descriptions.Item label={t('fields.cardNumber')}>
                            {data.bank_card_number}
                        </Descriptions.Item>
                    )}
                    {data.bank_card_holder_name && (
                        <Descriptions.Item label={t('fields.cardHolderName')}>
                            {data.bank_card_holder_name}
                        </Descriptions.Item>
                    )}
                    {(data.bank_province || data.bank_city) && (
                        <Descriptions.Item label={t('fields.province') + ' / ' + t('fields.city')}>
                            {[data.bank_province, data.bank_city].filter(Boolean).join(' / ')}
                        </Descriptions.Item>
                    )}
                    <Descriptions.Item label={t('fields.locked')}>
                        {data.locked ? t('status.locked') : t('status.unlocked')}
                        {data.locked_by && ` (${data.locked_by.name})`}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('fields.createdAt')}>
                        {data.created_at ? dayjs(data.created_at).format('YYYY-MM-DD HH:mm:ss') : '-'}
                    </Descriptions.Item>
                    {data.confirmed_at && (
                        <Descriptions.Item label={t('fields.successTime')}>
                            {dayjs(data.confirmed_at).format('YYYY-MM-DD HH:mm:ss')}
                        </Descriptions.Item>
                    )}
                    {data.notified_at && (
                        <Descriptions.Item label="通知时间">
                            {dayjs(data.notified_at).format('YYYY-MM-DD HH:mm:ss')}
                        </Descriptions.Item>
                    )}
                    <Descriptions.Item label={t('fields.note')} span={3}>
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
                                            message: t('messages.updateNoteSuccess'),
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
                />
                {isPaufen && data.provider_fees?.length > 0 && (
                    <>
                        <Typography.Title level={5} className="mt-4">
                            码商及码商代理手续费、利润列表
                        </Typography.Title>
                        <Table
                            dataSource={data.provider_fees}
                            rowKey={(record: ProviderFee) => record.provider.id}
                            columns={providerColumns}
                            pagination={false}
                        />
                    </>
                )}
            </Show>
        </>
    );
};

export default PayForAnotherShow;
