import { MinusSquareOutlined, PlusSquareOutlined } from "@ant-design/icons";
import {
    Row,
    useForm,
    Form,
    FormItemProps,
    Col,
    Button,
    TableColumnProps,
    PaginationProps,
    TableProps,
    Table,
    Grid,
} from "@pankod/refine-antd";
import {
    BaseRecord,
    CrudFilter,
    CrudFilters,
    GetListResponse,
    SuccessErrorNotification,
    useList,
    UseQueryOptions,
    useResource,
    useTranslate,
} from "@pankod/refine-core";
import { UseListProps } from "@pankod/refine-core/dist/hooks/data/useList";
import { FormProps } from "antd/lib/form/Form";
import dayjs, { Dayjs } from "dayjs";
import { cloneElement } from "react";
import { isValidElement, useState } from "react";

type Props<TData = any> = {
    formItems?: (FormItemProps & { collapse?: boolean })[];
    columns?: TableColumnProps<TData>[];
    resource?: string;
    filters?: CrudFilters;
    hasPagination?: boolean;
    queryOptions?: UseQueryOptions<GetListResponse<TData>, any>;
    showError?: boolean;
    transferValues?: (values: any) => any;
    errorNotification?: SuccessErrorNotification | undefined;
    tableProps?: TableProps<TData>;
};

function useTable<TData extends BaseRecord = any, Meta = any>({
    formItems: items,
    columns,
    resource,
    filters: outterFilters,
    hasPagination = true,
    queryOptions,
    showError = true,
    transferValues,
    tableProps,
}: Props<TData>) {
    const { resourceName } = useResource();
    const t = useTranslate();
    const breakpoint = Grid.useBreakpoint();
    const { form } = useForm();
    const [query, setQuery] = useState<Record<string, any>>({});
    const filters: CrudFilters = [
        ...(outterFilters || []),
        ...Object.entries(query)
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
                    };
                } else
                    return {
                        field,
                        value,
                        operator: "eq",
                    };
            }),
    ];
    const [pagination, setPagination] = useState<PaginationProps>({
        current: 1,
        pageSize: 20,
        showSizeChanger: false,
    });
    const listProps: UseListProps<TData, any> = {
        resource: resource || resourceName,
        config: {
            hasPagination,
            filters,
            pagination: hasPagination ? pagination : undefined,
        },
        queryOptions,
    };
    if (showError === false) {
        listProps.errorNotification = false;
    }
    const { data, isFetching, refetch, ...others } = useList<TData>(listProps);

    const $tableProps: TableProps<TData> = {
        dataSource: data?.data,
        pagination: hasPagination
            ? {
                  ...pagination,
                  total: data?.total,
                  onChange: (page) => {
                      setPagination({
                          ...pagination,
                          current: page,
                      });
                  },
                  position: ["bottomCenter"],
              }
            : false,
        columns,
        loading: isFetching,
        size: "small",
        rowKey: "id",
        ...tableProps,
    };

    const AntdForm = (props: FormProps) => {
        const [isCollapse, setIsCollapse] = useState(true);
        const formItems = items
            ?.filter((item) => !item.collapse)
            .map(({ children, ...otherProps }, index) => {
                const cloneChild = isValidElement(children)
                    ? cloneElement(children, {
                          showTime: true,
                          allowClear: true,
                          ...children.props,
                      })
                    : null;
                return (
                    <Col xs={24} md={6} key={index}>
                        <Form.Item {...otherProps}>{cloneChild}</Form.Item>
                    </Col>
                );
            });
        const collapseFormItems = items
            ?.filter((item) => item.collapse)
            .map(({ children, ...otherProps }, index) => {
                const cloneChild = isValidElement(children)
                    ? cloneElement(children, {
                          showTime: true,
                          allowClear: true,
                          ...children.props,
                      })
                    : null;
                return (
                    <Col xs={24} md={6} key={index}>
                        <Form.Item {...otherProps}>{cloneChild}</Form.Item>
                    </Col>
                );
            });
        return (
            <Form
                layout="vertical"
                form={form}
                className="bg-white p-4"
                onFinish={(values: Record<string, any>) => {
                    Object.entries(values).forEach(([key, value]) => {
                        if (value instanceof dayjs) {
                            values[key] = (value as Dayjs).format();
                        }
                    });
                    if (pagination.current !== 1) {
                        setPagination({
                            ...pagination,
                            current: 1,
                        });
                    }
                    values = transferValues?.(values) ?? values;
                    setQuery({ ...values, hash: dayjs().format() });
                }}
                {...props}
            >
                {collapseFormItems?.length ? (
                    <div className="flex justify-end pb-4">
                        {isCollapse ? (
                            <PlusSquareOutlined className="text-xl" onClick={() => setIsCollapse(false)} />
                        ) : (
                            <MinusSquareOutlined className="text-xl" onClick={() => setIsCollapse(true)} />
                        )}
                    </div>
                ) : null}

                <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
                    {formItems}
                </Row>
                <div className={`${isCollapse ? "scale-y-0 h-0" : "scale-y-100 h-auto"} transition-all origin-top`}>
                    {collapseFormItems?.length ? (
                        <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
                            {collapseFormItems}
                        </Row>
                    ) : null}
                </div>
                <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
                    <Col xs={24} md={6}>
                        <Row gutter={8}>
                            <Col span={12}>
                                <Button type="primary" block htmlType="submit" loading={isFetching}>
                                    {t("submit")}
                                </Button>
                            </Col>
                            <Col span={12}>
                                <Button
                                    block
                                    onClick={() => {
                                        form.resetFields();
                                        let values = form.getFieldsValue() as Record<string, any>;
                                        Object.entries(values).forEach(([key, value]) => {
                                            if (value instanceof dayjs) {
                                                values[key] = (value as Dayjs).format();
                                            }
                                        });
                                        if (pagination.current !== 1) {
                                            setPagination({
                                                ...pagination,
                                                current: 1,
                                            });
                                        }
                                        values = transferValues?.(values) ?? values;
                                        setQuery(values);
                                    }}
                                >
                                    {t("clear")}
                                </Button>
                            </Col>
                        </Row>
                    </Col>
                </Row>
            </Form>
        );
    };

    const AntdTable = (props: TableProps<TData>) => {
        return (
            <div
                style={{
                    overflowX: "auto",
                    maxWidth: breakpoint.xs || breakpoint.sm || breakpoint.md ? "calc(100vw - 24px)" : "auto",
                }}
            >
                <Table
                    dataSource={data?.data}
                    pagination={
                        hasPagination
                            ? {
                                  ...pagination,
                                  total: data?.total,
                                  onChange: (page) => {
                                      setPagination({
                                          ...pagination,
                                          current: page,
                                      });
                                  },
                                  position: ["bottomCenter"],
                              }
                            : false
                    }
                    columns={columns}
                    loading={isFetching}
                    size="small"
                    rowKey={"id"}
                    {...props}
                />
            </div>
        );
    };

    AntdTable.Column = Table.Column;
    AntdTable.Summary = Table.Summary;
    AntdForm.Item = Form.Item;
    AntdForm.useWatch = Form.useWatch;

    return {
        data: data?.data,
        meta: (data as any)?.meta as Meta,
        form,
        Form: AntdForm,
        Table: AntdTable,
        refetch,
        pagination,
        filters,
        tableProps: $tableProps,
        ...others,
    };
}

export default useTable;
