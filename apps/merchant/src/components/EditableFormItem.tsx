import { CheckOutlined, CloseOutlined, EditOutlined } from "@ant-design/icons";
import { Form, Modal, Space } from "antd";
import { useForm } from "@refinedev/antd";
import { useResource, useUpdate } from "@refinedev/core";
import React, { FC, PropsWithChildren, useState } from "react";

type Props = {
    defaultValue?: any;
    id: number | string;
    resource?: string;
    name?: string;
    label?: string;
};

const EditableForm: FC<PropsWithChildren<Props>> = ({ children, resource, id, name, label }) => {
    const [isEditing, setIsEditing] = useState(false);
    const { formProps, form } = useForm();
    const { mutateAsync } = useUpdate();
    const { resource: resourceInfo } = useResource();
    const resourceName = resourceInfo?.name ?? "";
    return (
        <Form {...formProps}>
            <Space align="center">
                <Form.Item name={name} label={label} className="m-0">
                    {React.isValidElement(children)
                        ? React.cloneElement<any>(children, {
                              disabled: !isEditing,
                          })
                        : null}
                </Form.Item>
                {isEditing ? (
                    <>
                        <CheckOutlined
                            onClick={() => {
                                Modal.confirm({
                                    title: "确定要修改嗎",
                                    okText: "确定",
                                    cancelText: "取消",
                                    onOk: async () => {
                                        await form.validateFields();
                                        await mutateAsync({
                                            id,
                                            values: {
                                                ...form.getFieldsValue(),
                                                id,
                                            },
                                            resource: resource || resourceName,
                                            successNotification: {
                                                message: "修改成功",
                                                type: "success",
                                            },
                                        });
                                        setIsEditing(false);
                                    },
                                });
                            }}
                        />
                        <CloseOutlined onClick={() => setIsEditing(false)} />
                    </>
                ) : null}
                {!isEditing ? <EditOutlined onClick={() => setIsEditing(true)} /> : null}
            </Space>
        </Form>
    );
};

export default EditableForm;
