import {
  Create,
  useForm,
} from '@refinedev/antd';
import {
  Col,
  Form,
  Input,
  Row,
  Button,
} from 'antd';
import { useNavigation } from "@refinedev/core";
import { FC } from "react";
import {useTranslation} from "react-i18next";

const TagCreate: FC = (props) => {
    const {t} = useTranslation()
    const { form, formProps } = useForm({
        action: "create",
    });
    const { list } = useNavigation();
    return (
        <Create
            title={t("tagsPage.buttons.create")}
            footerButtons={() => (
                <>
                    <Button
                        onClick={() => {
                            list("tags");
                        }}
                    >
                        {t("cancel")}
                    </Button>
                    <Button type="primary" onClick={form.submit}>
                        {t("submit")}
                    </Button>
                </>
            )}
        >
            <Form {...formProps}>
                <Row gutter={16}>
                    <Col xs={24} md={12}>
                        <Form.Item name={"name"} label={t("tagsPage.fields.name")} rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Create>
    );
};

export default TagCreate;
