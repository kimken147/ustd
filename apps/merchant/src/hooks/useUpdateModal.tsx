import { Form, Modal, FormItemProps, FormProps } from "antd";
import type { ModalFuncProps } from "antd";
import { useForm, useModal } from "@refinedev/antd";
import {
    BaseRecord,
    HttpError,
    useCreate,
    useCustomMutation,
    useDelete,
    useResource,
    useTranslate,
    useUpdate,
} from "@refinedev/core";
import { PropsWithChildren, useState } from "react";

type Props = {
    onSuccess?: (data: BaseRecord) => void;
    confirmTitle?: string;
    resource?: string;
    transferFormValues?: (record: Record<string, any>) => Record<string, any>;
    formItems: FormItemProps[];
    mode?: "create" | "update";
    onOk?: () => void;
    children?: React.ReactNode;
};

type UpdateModalProps = {
    defaultValue?: Record<string, any>;
    children?: React.ReactNode;
};

type Config = {
    id?: string | number;
    filterFormItems?: NamePath[];
    title: string;
    formValues?: any;
    mode?: "create" | "update";
    resource?: string;
    initialValues?: any;
    confirmTitle?: string;
    customMutateConfig?: {
        url: string;
        values?: any;
        method: "post" | "put" | "patch" | "delete";
    };
    successMessage?: string;
    children?: React.ReactNode;
    onSuccess?: () => void;
    onConfirm?: (values: any) => void;
};

function useUpdateModal<TData extends BaseRecord>(props?: Props) {
    const t = useTranslate();
    const { resource: resourceInfo } = useResource();
    const resourceName = resourceInfo?.name ?? "";
    const { mutateAsync: customMutate } = useCustomMutation();
    const { mutate, mutateAsync, isPending, ...others } = useUpdate<TData>();
    const { mutate: mutateDeleting } = useDelete();
    const { mutateAsync: create } = useCreate();
    const { form } = useForm();
    const [config, setConfig] = useState<Config>();
    const mode = config?.mode || "update";

    const onSubmit = async () => {
        try {
            await form?.validateFields();
            const values = { ...form?.getFieldsValue(), id: config?.id, ...config?.formValues };
            if (config?.onConfirm) {
                config?.onConfirm(values);
                return;
            }
            if (config?.customMutateConfig) {
                const data = await customMutate({
                    ...config.customMutateConfig,
                    values: {
                        ...config.customMutateConfig.values,
                        ...form?.getFieldsValue(),
                    },
                    successNotification: config.successMessage
                        ? {
                              message: config.successMessage,
                              type: "success",
                          }
                        : undefined,
                });
                props?.onSuccess?.(data);
                config.onSuccess?.();
            } else {
                const operator = mode === "update" ? mutateAsync : create;
                await operator(
                    {
                        id: config?.id ?? 0,
                        values: props?.transferFormValues?.(values) || values,
                        resource: config?.resource ?? props?.resource ?? resourceName,
                        successNotification: {
                            message: t("success"),
                            type: "success",
                        },
                    },
                    {
                        onSuccess(data) {
                            props?.onSuccess?.(data);
                            config?.onSuccess?.();
                        },
                    },
                );
            }
            close();
            return Promise.resolve();
        } catch (error) {
            console.log(error);
        } finally {
            form.resetFields();
        }
    };

    const {
        modalProps,
        show: showModal,
        close,
    } = useModal({
        modalProps: {
            title: config?.title,
            destroyOnClose: true,
            okText: t("submit"),
            cancelText: t("cancel"),
            children: (
                <Form form={form} layout="vertical">
                    {props?.formItems
                        .filter((formItem) => {
                            if (!config?.filterFormItems?.length) return true;
                            return config?.filterFormItems.includes(formItem.name as any);
                        })
                        .map((formItem, key) => (
                            <Form.Item
                                key={`${formItem.name}-${key}`}
                                {...formItem}
                                className={`w-full ${formItem.className || ""}`}
                            ></Form.Item>
                        ))}
                    {config?.children}
                </Form>
            ),
            onOk:
                props?.onOk ??
                async function () {
                    Modal.confirm({
                        title: config?.confirmTitle ?? props?.confirmTitle ?? t("confirmModify"),
                        onOk: onSubmit,
                        okText: t("ok"),
                        cancelText: t("cancel"),
                        okButtonProps: {
                            loading: isPending,
                        },
                    });
                },
            onCancel() {
                form.resetFields();
            },
            okButtonProps: {
                loading: isPending,
            },
        },
    });

    const show = (config: Config) => {
        setConfig(config);
        if (config.initialValues) {
            form.setFieldsValue(config.initialValues);
        }
        showModal();
    };

    const FormComponent = (props: PropsWithChildren<FormProps>) => {
        return <Form form={form} initialValues={config?.initialValues} {...props}></Form>;
    };

    FormComponent.Item = Form.Item;

    function UpdateModal({ defaultValue }: UpdateModalProps) {
        return (
            <Modal {...modalProps}>
                <Form form={form} layout="vertical" initialValues={defaultValue}>
                    {props?.formItems
                        .filter((formItem) => {
                            if (!config?.filterFormItems?.length) return true;
                            return config?.filterFormItems.includes(formItem.name as any);
                        })
                        .map((formItem, key) => (
                            <Form.Item
                                key={`${formItem.name}-${key}`}
                                {...formItem}
                                className={`w-full ${formItem.className || ""}`}
                            ></Form.Item>
                        ))}
                    {config?.children}
                </Form>
            </Modal>
        );
    }

    UpdateModal.confirm = ({
        id,
        values,
        resource,
        mode = "update",
        onSuccess,
        customMutateConfig,
        ...modalProps
    }: ModalFuncProps & {
        values?: any;
        id: string | number;
        resource?: string;
        mode?: "update" | "delete";
        onSuccess?: <TData extends BaseRecord>(data?: TData) => void;
        customMutateConfig?: {
            url: string;
            method: "post" | "put" | "patch" | "delete";
        };
    }) => {
        Modal.confirm({
            okText: t("submit"),
            cancelText: t("cancel"),
            onOk: async () => {
                if (customMutateConfig) {
                    await customMutate(
                        {
                            ...customMutateConfig,
                            values,
                        },
                        {
                            onSuccess(data, variables, context) {
                                onSuccess?.(data.data);
                            },
                        },
                    );

                    return;
                }
                if (mode === "update") {
                    mutate(
                        {
                            id,
                            values: {
                                ...values,
                                id,
                            },
                            resource: resource || resourceName,
                            successNotification: {
                                message: t("success"),
                                type: "success",
                            },
                            errorNotification(error, values, resource) {
                                return {
                                    message: (error as HttpError)?.message || t("errors.tryLater"),
                                    type: "error",
                                };
                            },
                        },
                        {
                            onSuccess(data) {
                                onSuccess?.(data.data);
                            },
                        },
                    );
                } else {
                    mutateDeleting(
                        {
                            id,
                            resource: resource || resourceName,
                            successNotification: {
                                message: t("success"),
                                type: "success",
                            },
                            errorNotification: {
                                message: t("errros.tryLater"),
                                type: "error",
                            },
                            values: {
                                id,
                                ...values,
                            },
                        },
                        {
                            onSuccess(data) {
                                onSuccess?.(data.data);
                            },
                        },
                    );
                }
            },
            okButtonProps: {
                loading: isPending,
            },
            ...modalProps,
        });
    };

    return {
        Modal: UpdateModal,
        show,
        Form: FormComponent,
        form,
        modalProps,
        ...others,
    };
}

export default useUpdateModal;
