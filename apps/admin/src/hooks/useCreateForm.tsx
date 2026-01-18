import { Col, Form as AntdForm, Row } from "antd";
import type { FormItemProps } from "antd";
import { useForm } from "@refinedev/antd";

type Props = {
    formItems: FormItemProps[];
};

function useCreateForm({ formItems }: Props) {
    const { formProps } = useForm();
    const Form = () => {
        return (
            <AntdForm {...formProps}>
                <Row gutter={16}>
                    {formItems.map((formItem, index) => (
                        <Col xs={24} md={12} lg={8}>
                            <AntdForm.Item key={`${formItem.name} - ${index}`} {...formItem} />
                        </Col>
                    ))}
                </Row>
            </AntdForm>
        );
    };

    return {
        Form,
        formProps,
    };
}

export default useCreateForm;
