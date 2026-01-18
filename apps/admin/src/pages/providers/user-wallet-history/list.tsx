import { EditOutlined, InfoCircleOutlined } from "@ant-design/icons";
import {
    DateField,
    DatePicker,
    Divider,
    Input,
    List,
    Popover,
    ShowButton,
    Space,
    TableColumnProps,
    TextField,
} from "@pankod/refine-antd";
import { useGetIdentity, useOne } from "@pankod/refine-core";
import { useSearchParams } from "@pankod/refine-react-router-v6";
import CustomDatePicker from "components/customDatePicker";
import dayjs, { Dayjs } from "dayjs";
import useUserWalletStatus from "hooks/userUserWalletStatus";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { MerchantWalletOperator as Operator, User, Format } from "@morgan-ustd/shared";
import { UserWalletHistory } from "interfaces/userWalletHistory";
import { getSign } from "lib/number";
import { FC } from "react";
import { Helmet } from "react-helmet";
import { useTranslation } from "react-i18next";

const ProviderUserWalletHistoryList: FC = () => {
    const { t } = useTranslation('providers');
    const { data: profile } = useGetIdentity<Profile>();
    const defaultStartAt = dayjs().startOf("days");
    const [searchParams] = useSearchParams();
    const userId = searchParams.get("user_id");
    const { data: user } = useOne<User>({
        resource: "users",
        id: userId || 0,
    });
    const { Select: UserWalletSelect, getUserWalletStatusText } = useUserWalletStatus();

    const { Form, Table, form } = useTable<UserWalletHistory>({
        resource: `users/${userId}/wallet-histories`,
        filters: [
            {
                field: "started_at",
                operator: "eq",
                value: defaultStartAt.format(),
            },
            {
                field: "user_id",
                operator: "eq",
                value: userId,
            },
        ],
        formItems: [
            {
                label: t('walletHistory.startDate'),
                name: "started_at",
                trigger: "onSelect",
                children: (
                  <CustomDatePicker
                    showTime
                    className="w-full"
                    onFastSelectorChange={(startAt, endAt) =>
                      form.setFieldsValue({
                          started_at: startAt,
                          ended_at: endAt,
                      })
                    }
                  />
                ),
                rules: [
                    {
                        required: true,
                    },
                ],
            },
            {
                label: t('walletHistory.endDate'),
                name: "ended_at",
                trigger: "onSelect",
                children: (
                  <DatePicker
                    showTime
                    className="w-full"
                    disabledDate={(current) => {
                        const startAt = form.getFieldValue("started_at") as Dayjs;
                        return current && (current > startAt.add(1, "month") || current < startAt);
                    }}
                  />
                ),
            },
            {
                label: t('walletHistory.alterationType'),
                name: "type[]",
                children: <UserWalletSelect mode="multiple" />,
            },
            {
                label: t('walletHistory.note'),
                name: "note",
                children: <Input />,
            },
        ],
    });
    const { Modal, show } = useUpdateModal({
        formItems: [
            {
                label: t('walletHistory.note'),
                name: "note",
                children: <Input.TextArea />,
                rules: [{ required: true }],
            },
        ],
    });
    if (!userId) return null;

    const columns: TableColumnProps<UserWalletHistory>[] = [
        {
            title: t('walletHistory.alterationType'),
            dataIndex: "type",
            render(value, record, index) {
                return getUserWalletStatusText(value);
            },
        },
        {
            title: t('walletHistory.balanceDelta'),
            dataIndex: "balance_delta",
            render(value, record, index) {
                return getSign(value);
            },
        },
        {
            title: t('walletHistory.profitDelta'),
            dataIndex: "profit_delta",
            render(value, record, index) {
                return getSign(value);
            },
        },
        {
            title: t('walletHistory.frozenBalanceDelta'),
            dataIndex: "frozen_balance_delta",
            render(value, record, index) {
                return getSign(value);
            },
        },
        {
            title: t('walletHistory.balanceResult'),
            dataIndex: "balance_result",
        },
        {
            title: t('walletHistory.profitResult'),
            dataIndex: "profit_result",
        },
        {
            title: t('walletHistory.frozenBalanceResult'),
            dataIndex: "frozen_balance_result",
        },

        {
            title: t('walletHistory.note'),
            dataIndex: "note",
            render(value, record, index) {
                return (
                  <Space>
                      <TextField value={value} />
                      <EditOutlined
                        style={{
                            color: "#6eb9ff",
                        }}
                        onClick={() =>
                          show({
                              title: t('walletHistory.editNote'),
                              id: record.id,
                              resource: `users/${userId}/wallet-histories`,
                              filterFormItems: ["note"],
                              initialValues: {
                                  note: record.note,
                              },
                          })
                        }
                      />
                  </Space>
                );
            },
        },
        {
            title: t('walletHistory.alterationTime'),
            dataIndex: "created_at",
            render(value, record, index) {
                return value ? <DateField value={value} format={Format} /> : null;
            },
        },
        {
            title: t('walletHistory.operator'),
            dataIndex: "operator",
            render(value: Operator, record, index) {
                if (!value) return;
                return (
                  <Space>
                      {value?.role === 1 ? (
                        <TextField value={value?.username} />
                      ) : (
                        <ShowButton
                          recordItemId={value?.id}
                          disabled={profile?.role !== 1}
                          resourceNameOrRouteName="sub-accounts"
                          icon={null}
                        >
                            {value?.username}
                        </ShowButton>
                      )}

                      <Popover trigger={"click"} content={<TextField value={t('walletHistory.operatorInfo', { name: value?.name })} />}>
                          <InfoCircleOutlined className="text-[#1677ff]" />
                      </Popover>
                  </Space>
                );
            },
        },
    ];
    return (
      <>
          <Helmet>
              <title>{t('walletHistory.title')}</title>
          </Helmet>
          <List
            title={
                <Space align="center">
                    <ShowButton
                      size="large"
                      icon={null}
                      recordItemId={user?.data.id}
                      resourceNameOrRouteName="providers"
                    >
                        {user?.data.name}
                    </ShowButton>
                    {" - "}
                    <TextField value={t('walletHistory.title')} strong className="text-xl" />
                </Space>
            }
          >
              <Form
                initialValues={{
                    started_at: defaultStartAt,
                }}
              />
              <Divider />
              <Table columns={columns} />
          </List>
          <Modal />
      </>
    );
};

export default ProviderUserWalletHistoryList;