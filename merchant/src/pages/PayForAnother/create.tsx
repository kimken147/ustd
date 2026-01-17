import { MinusCircleOutlined, SaveOutlined } from "@ant-design/icons";
import {
    Button,
    Col,
    Create,
    Form,
    Input,
    InputNumber,
    Modal,
    Row,
    Select,
    message,
    useForm,
} from "@pankod/refine-antd";
import { useCreate, useGetIdentity, useNavigation, useNotification, useTranslate } from "@pankod/refine-core";
import useSelector from "hooks/useSelector";
import useUpdateModal from "hooks/useUpdateModal";
import { SelectOptions } from "interfaces/antd";
import { Bank } from "interfaces/bank";
import { sumBy } from "lodash";
import numeral from "numeral";
import { FC, useState } from "react";
import { Helmet } from "react-helmet";

const PayForAnotherCreate: FC = () => {
    const translate = useTranslate();
    const title = translate("withdraw.buttons.createPayment");
    const { data: profile } = useGetIdentity<Profile>();
    const { goBack } = useNavigation();
    const { form } = useForm<any, any, { lists: any[] }>();
    const { Select: BankSelect } = useSelector<Bank>({
        resource: "banks",
        valueField: "name",
    });
    const { modalProps, show } = useUpdateModal({
        formItems: [
            {
                label: translate("verificationCode"),
                name: "one_time_password",
                children: <Input />,
                rules: [
                    {
                        required: true,
                    },
                ],
            },
        ],
    });
    const lists = Form.useWatch("lists", form);
    const selectOptions: SelectOptions = [
        {
            label: translate("channels.BANK_CARD"),
            value: "BANK_CARD",
        },
        // {
        //     label: "GCash",
        //     value: "GCASH",
        // },
        // {
        //     label: "Maya",
        //     value: "MAYA",
        // },
        // {
        //     label: "支付宝",
        //     value: "QR_ALIPAY",
        // },
        // {
        //     label: "USDT",
        //     value: "USDT",
        // },
    ];
    const getCardLabel = (index: number) => {
        const type = lists?.[index]?.type;
        switch (type) {
            case "BANK_CARD":
                return translate("withdraw.fields.bankAccount");
            case "GCASH":
                return translate("withdraw.fields.gcashAccount");
            case "MAYA":
                return translate("withdraw.fields.mayaAccount");
            case "QR_ALIPAY":
                return translate("withdraw.fields.alipayAccount");
            case "USDT":
                return translate("withdraw.fields.walletAddress");
        }
        return "";
    };

    const { open } = useNotification();
    const { mutateAsync } = useCreate();
    const [isSubmitLoading, setIsSubmitLoading] = useState(false);

    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Create
                title={title}
                footerButtons={() => (
                    <>
                        <Button loading={isSubmitLoading} type="primary" icon={<SaveOutlined />} onClick={form.submit} disabled={isSubmitLoading}>
                            {translate("submit")}
                        </Button>
                    </>
                )}
            >
                <Form
                    form={form}
                    layout="vertical"
                    onFinish={async (values) => {
                        if (isSubmitLoading) return;
                        const submit = async (google?: any) => {
                            setIsSubmitLoading(true);
                            const totalAmount = sumBy(values.lists, (record) => +record.amount);
                            if (totalAmount > (profile?.wallet ? numeral(profile.wallet.balance).value() ?? 0 : 0)) {
                                message.error({
                                    content: translate("withdraw.errors.totalAmountExceedsBalance"),
                                });
                                setIsSubmitLoading(false);
                                return;
                            }
                            for (let item of values.lists) {
                                if (item.type !== "BANK_CARD") {
                                    if (item.type === "GCASH") {
                                        item.bank_name = "GCash";
                                    } else if (item.type === "QR_ALIPAY") item.bank_name = translate("channels.QR_ALIPAY");
                                    else item.bank_name = item.type;
                                }
                                try {
                                    await mutateAsync({
                                        resource: "agency-withdraws",
                                        values: {
                                            ...item,
                                            ...google,
                                        },
                                        successNotification: false,
                                    });
                                } catch (error) {}
                            }
                            setIsSubmitLoading(false);
                            open?.({
                                message: translate("withdraw.create.messages.success"),
                                type: "success",
                            });
                            goBack();
                        };
                        if (profile?.withdraw_google2fa_enable) {
                            show({
                                title: translate("withdraw.create.fields.code"),
                                id: 0,
                                onConfirm(google) {
                                    submit(google);
                                },
                            });
                        } else submit();
                    }}
                >
                    <Form.List
                        name={"lists"}
                        initialValue={[
                            {
                                type: "BANK_CARD",
                            },
                        ]}
                    >
                        {(fields, { add, remove }, { errors }) => {
                            return (
                                <>
                                    {fields.map(({ key, name, ...rest }, index) => {
                                        const isBankCard = lists?.[index]?.type === "BANK_CARD";
                                        return (
                                            <Row gutter={16} className="mb-4" key={key}>
                                                <Col xs={24} md={12} lg={2}>
                                                    <Form.Item
                                                        name={[name, "type"]}
                                                        label={(index + 1).toString()}
                                                        {...rest}
                                                    >
                                                        <Select options={selectOptions} />
                                                    </Form.Item>
                                                </Col>
                                                <Col xs={24} md={12} lg={2}>
                                                    <Form.Item
                                                        label={translate("amount")}
                                                        {...rest}
                                                        rules={[{ required: true }, { min: 1, type: "number" }]}
                                                        name={[name, "amount"]}
                                                    >
                                                        <InputNumber className="w-full" />
                                                    </Form.Item>
                                                </Col>
                                                {isBankCard ? (
                                                    <Col xs={24} md={12} lg={3}>
                                                        <Form.Item
                                                            {...rest}
                                                            name={[name, "bank_name"]}
                                                            label={translate("withdraw.fields.bankName")}
                                                            rules={[{ required: true }]}
                                                        >
                                                            <BankSelect />
                                                        </Form.Item>
                                                    </Col>
                                                ) : null}
                                                <Col xs={24} md={12} lg={isBankCard ? 4 : 6}>
                                                    <Form.Item
                                                        label={getCardLabel(index)}
                                                        name={[name, "bank_card_number"]}
                                                        rules={[{ required: true }]}
                                                        {...rest}
                                                    >
                                                        <Input />
                                                    </Form.Item>
                                                </Col>
                                                <Col xs={24} md={12} lg={isBankCard ? 3 : 6}>
                                                    <Form.Item
                                                        {...rest}
                                                        name={[name, "bank_card_holder_name"]}
                                                        label={translate("withdraw.fields.accountOwner")}
                                                        rules={[{ required: isBankCard }]}
                                                    >
                                                        <Input />
                                                    </Form.Item>
                                                </Col>
                                                {isBankCard ? (
                                                    <Col xs={24} md={12} lg={3}>
                                                        <Form.Item
                                                            label={translate("withdraw.fields.province")}
                                                            {...rest}
                                                            name={[name, "bank_province"]}
                                                        >
                                                            <Input placeholder={translate("optional")} />
                                                        </Form.Item>
                                                    </Col>
                                                ) : null}
                                                {isBankCard ? (
                                                    <Col xs={24} md={12} lg={3}>
                                                        <Form.Item
                                                            label={translate("withdraw.fields.city")}
                                                            {...rest}
                                                            name={[name, "bank_city"]}
                                                        >
                                                            <Input placeholder={translate("optional")} />
                                                        </Form.Item>
                                                    </Col>
                                                ) : null}
                                                <Col xs={24} md={12} lg={3}>
                                                    <Form.Item
                                                        label={translate(
                                                            "withdraw.create.fields.merchantTransactionNo",
                                                        )}
                                                        {...rest}
                                                        name={[name, "order_id"]}
                                                    >
                                                        <Input placeholder={translate("optional")} />
                                                    </Form.Item>
                                                </Col>
                                                <Col xs={24} md={12} lg={1}>
                                                    <Form.Item label=" ">
                                                        <MinusCircleOutlined
                                                            className="text-2xl pt-1"
                                                            onClick={() => remove(name)}
                                                        />
                                                    </Form.Item>
                                                </Col>
                                            </Row>
                                        );
                                    })}
                                    <Row gutter={16} align="middle">
                                        <Form.Item>
                                            <Button
                                                type="dashed"
                                                onClick={() =>
                                                    add({
                                                        type: "BANK_CARD",
                                                    })
                                                }
                                            >
                                                {translate("add")}
                                            </Button>
                                            <Form.ErrorList errors={errors} />
                                        </Form.Item>
                                    </Row>
                                </>
                            );
                        }}
                    </Form.List>
                </Form>
            </Create>
            <Modal {...modalProps} />
        </>
    );
};

export default PayForAnotherCreate;
