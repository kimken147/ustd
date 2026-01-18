import { Row, Form, Col, Button } from "antd";
import type { FormItemProps, FormProps } from "antd";
import { useForm } from "@refinedev/antd";
import { CrudFilter, CrudFilters } from "@refinedev/core";

type FormItem = FormItemProps;

const useQueryForm = ({ items, showButtons = true }: { items: FormItem[]; showButtons?: boolean }) => {
    const { form } = useForm();
    const filters: CrudFilters = Object.entries(form.getFieldsValue())
        .filter(([_, value]) => value !== undefined)
        .map<CrudFilter>(([field, value]: [string, any]) => {
            if (Array.isArray(value)) {
                return {
                    operator: "or",
                    value: value.map<CrudFilter>((x: any) => ({
                        field,
                        value: x,
                        operator: "eq",
                    })),
                    field,
                };
            } else
                return {
                    field,
                    value,
                    operator: "eq",
                };
        });
    const AntdForm = (props: FormProps) => (
        <Form layout="vertical" form={form} className="bg-white p-4" {...props}>
            <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
                {items.map(({ children, ...otherProps }, index) => (
                    <Col xs={24} md={6} key={index}>
                        <Form.Item {...otherProps}>{children}</Form.Item>
                    </Col>
                ))}
                {showButtons && (
                    <Col xs={24} md={6}>
                        <Row gutter={8}>
                            <Col span={12}>
                                <Button type="primary" block htmlType="submit">
                                    提交
                                </Button>
                            </Col>
                            <Col span={12}>
                                <Button block onClick={() => form.resetFields()}>
                                    清空
                                </Button>
                            </Col>
                        </Row>
                    </Col>
                )}
            </Row>
        </Form>
    );

    return {
        form,
        Form: AntdForm,
        filters,
    };
};

export default useQueryForm;
