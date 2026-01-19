import { Form as AntdForm, Modal } from "antd";
import type { FormItemProps, FormProps, ModalFuncProps } from "antd";
import { useForm, useModal } from "@refinedev/antd";
import { BaseRecord, useCreate, useCustomMutation, useDelete, useResourceParams, useUpdate } from "@refinedev/core";
import { PropsWithChildren, useState } from "react";
import { useTranslation } from "react-i18next";

type Props = {
    onSuccess?: (data: BaseRecord) => void;
    confirmTitle?: string;
    resource?: string;
    transferFormValues?: (record: Record<string, any>) => Record<string, any>;
    formItems: FormItemProps[];
    mode?: "create" | "update";
    onCancel?: () => void;
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
    customMutateConfig?:
        | {
              url: string;
              values?: any;
              method: "post" | "put" | "patch" | "delete";
              mutiple?: Array<{
                  id: string | number;
                  url: string;
              }>;
          }
        | {
              url?: string;
              values?: any;
              method: "post" | "put" | "patch" | "delete";
              mutiple: Array<{
                  id: string | number;
                  url: string;
              }>;
          };
    successMessage?: string;
    children?: React.ReactNode;
    onSuccess?: () => void;
    confirmTitle?: string;
};

function useUpdateModal<TData extends BaseRecord>(props?: Props) {
    const { t } = useTranslation();
    const { resource } = useResourceParams();
    const resourceName = resource?.name;
    const { mutateAsync: customMutate } = useCustomMutation();
    const { mutate, mutateAsync, mutation, ...others } = useUpdate<TData>();
    const isLoading = mutation.isPending;
    const { mutate: mutateDeleting } = useDelete();
    const { mutateAsync: create } = useCreate();
    const { form } = useForm();
    const [config, setConfig] = useState<Config>();
    const mode = config?.mode || "update";

    const onSubmit = async () => {
        try {
            await form?.validateFields();
            const values = { ...form?.getFieldsValue(), id: config?.id, ...config?.formValues };
            if (config?.customMutateConfig) {
                const { url, mutiple } = config.customMutateConfig;
                if (mutiple) {
                    const promises: Promise<any>[] = [];
                    for (let item of mutiple) {
                        promises.push(
                            customMutate({
                                ...config.customMutateConfig,
                                url: item.url,
                                values: {
                                    ...values,
                                    id: item.id,
                                },
                            }),
                        );
                    }
                    await Promise.all(promises);
                    config.onSuccess?.();
                } else {
                    const data = await customMutate({
                        ...config.customMutateConfig,
                        url: url!,
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
                }
            } else {
                const operator = mode === "update" ? mutateAsync : create;
                await operator(
                    {
                        id: config?.id ?? 0,
                        values: props?.transferFormValues?.(values) || values,
                        resource: config?.resource ?? props?.resource ?? resourceName,
                        successNotification: {
                            message: mode === "update" ? t("updateSuccess") : t("createSuccess"),
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

    const onCancel = () => {
        form.resetFields();
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
                <AntdForm form={form} layout="vertical">
                    {props?.formItems
                        .filter((formItem) => {
                            if (!config?.filterFormItems?.length) return true;
                            return config?.filterFormItems.includes(formItem.name as any);
                        })
                        .map((formItem, key) => (
                            <AntdForm.Item
                                key={`${formItem.name}-${key}`}
                                {...formItem}
                                className={`w-full ${formItem.className || ""}`}
                            ></AntdForm.Item>
                        ))}
                    {config?.children}
                </AntdForm>
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
                            loading: isLoading,
                        },
                    });
                },
            onCancel: () => {
                props?.onCancel?.();
                onCancel();
            },
            okButtonProps: {
                loading: isLoading,
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

    const Form = (props: PropsWithChildren<FormProps>) => {
        return <AntdForm form={form} initialValues={config?.initialValues} {...props}></AntdForm>;
    };

    Form.Item = AntdForm.Item;

    function UpdateModal({ defaultValue }: UpdateModalProps) {
        return (
            <Modal {...modalProps}>
                <AntdForm form={form} layout="vertical" initialValues={defaultValue}>
                    {props?.formItems
                        .filter((formItem) => {
                            if (!config?.filterFormItems?.length) return true;
                            return config?.filterFormItems.includes(formItem.name as any);
                        })
                        .map((formItem, key) => (
                            <AntdForm.Item
                                key={`${formItem.name}-${key}`}
                                {...formItem}
                                className={`w-full ${formItem.className || ""}`}
                            ></AntdForm.Item>
                        ))}
                    {config?.children}
                </AntdForm>
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
            okText: t("ok"),
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
                                message: t("updateSuccess"),
                                type: "success",
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
                            resource: resource || resourceName || '',
                            successNotification: {
                                message: t("deleteSuccess"),
                                type: "success",
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
                loading: isLoading,
            },
            ...modalProps,
        });
    };

    return {
        Modal: UpdateModal,
        show,
        Form,
        form,
        modalProps,
        onCancel,
        ...others,
    };
}

export default useUpdateModal;
