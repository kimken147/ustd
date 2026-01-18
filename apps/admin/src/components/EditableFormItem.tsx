import { CheckOutlined, CloseOutlined, EditOutlined } from '@ant-design/icons';
import { Form, Modal, Space } from 'antd';
import type { FormItemProps } from 'antd';
import { useForm } from '@refinedev/antd';
import { useResource, useUpdate } from '@refinedev/core';
import React, { FC, PropsWithChildren, useState } from 'react';
import { useTranslation } from 'react-i18next';

type Props = Omit<FormItemProps, 'id'> & {
  initialValues?: any;
  id: number | string;
  resource?: string;
  disabled?: boolean;
};

const EditableForm: FC<PropsWithChildren<Props>> = ({
  children,
  resource,
  id,
  initialValues,
  disabled = false,
  ...formItemProps
}) => {
  const { t } = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const { formProps, form } = useForm();
  const { mutateAsync } = useUpdate();
  const { resourceName } = useResource();
  return (
    <Form {...formProps} initialValues={initialValues}>
      <Space align="center" className="w-full">
        <Form.Item className="m-0" {...formItemProps}>
          {React.isValidElement(children)
            ? React.cloneElement<any>(children, {
                disabled: !isEditing,
              })
            : null}
        </Form.Item>
        {disabled ? null : (
          <>
            {isEditing ? (
              <>
                <CheckOutlined
                  disabled
                  onClick={() => {
                    Modal.confirm({
                      title: t('confirmModify'),
                      okText: t('ok'),
                      cancelText: t('cancel'),
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
                            message: t('updateSuccess'),
                            type: 'success',
                          },
                        });
                        setIsEditing(false);
                      },
                    });
                  }}
                />
                <CloseOutlined
                  onClick={() => {
                    setIsEditing(false);
                    form.resetFields();
                  }}
                />
              </>
            ) : null}
            {!isEditing ? (
              <EditOutlined disabled onClick={() => setIsEditing(true)} />
            ) : null}
          </>
        )}
      </Space>
    </Form>
  );
};

export default EditableForm;
