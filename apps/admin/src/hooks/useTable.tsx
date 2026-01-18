import { MinusSquareOutlined, PlusSquareOutlined } from '@ant-design/icons';
import { useForm } from '@refinedev/antd';
import {
  Row,
  Form,
  Col,
  Button,
  Table,
  Grid,
} from 'antd';
import type { FormItemProps, TableColumnProps, PaginationProps, TableProps, FormProps } from 'antd';
import {
  BaseRecord,
  CrudFilter,
  CrudFilters,
  GetListResponse,
  SuccessErrorNotification,
  useList,
  useResource,
} from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import dayjs, { Dayjs } from 'dayjs';
import { CSSProperties, cloneElement } from 'react';
import { isValidElement, useState } from 'react';

type Props<TData = any> = {
  formItems?: (FormItemProps & { collapse?: boolean })[];
  columns?: TableColumnProps<TData>[];
  resource?: string;
  filters?: CrudFilters;
  hasPagination?: boolean;
  queryOptions?: any;
  showError?: boolean;
  transferValues?: (values: any) => any;
  errorNotification?: SuccessErrorNotification | undefined;
  tableProps?: Omit<TableProps<TData>, 'rowKey'> & { rowKey?: string | ((record: TData) => string) };
  onSubmit?: () => void;
  pagination?: TableProps<TData>['pagination'];
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
  onSubmit,
  pagination: propsPagination,
}: Props<TData>) {
  const [isCollapse, setIsCollapse] = useState(true);
  const { resourceName } = useResource();
  const breakpoint = Grid.useBreakpoint();
  const { form } = useForm();
  const { t } = useTranslation();
  const [query, setQuery] = useState<Record<string, any>>({});
  const filters: CrudFilters = [
    ...(outterFilters || []),
    ...Object.entries(query)
      .filter(
        ([_, value]) => value !== undefined && value !== '' && value !== null
      )
      .map<CrudFilter>(([field, value]: [string, any]) => {
        if (Array.isArray(value)) {
          return {
            operator: 'or',
            value: value.map<CrudFilter>((x: any) => ({
              field,
              value: x,
              operator: 'eq',
            })),
          };
        } else
          return {
            field,
            value,
            operator: 'eq',
          };
      }),
  ];
  const [pagination, setPagination] = useState<PaginationProps>({
    current: 1,
    pageSize: 20,
    showSizeChanger: true,
    pageSizeOptions: [20, 50, 100, 500],
    ...propsPagination,
  });
  const { data, isFetching, refetch, ...others } = useList<TData>({
    resource: resource || resourceName,
    pagination: hasPagination ? {
      current: pagination.current,
      pageSize: pagination.pageSize,
    } : undefined,
    filters,
    queryOptions,
    errorNotification: showError === false ? false : undefined,
  });

  const AntdForm = (props: FormProps) => {
    const formItems = items
      ?.filter(item => !item.collapse && !item.hidden)
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
      ?.filter(item => item.collapse && !item.hidden)
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
          onSubmit?.();
        }}
        {...props}
      >
        {collapseFormItems?.length ? (
          <div className="flex justify-end pb-4">
            {isCollapse ? (
              <PlusSquareOutlined
                className="text-xl"
                onClick={() => setIsCollapse(false)}
                style={{
                  color: '#6eb9ff',
                }}
              />
            ) : (
              <MinusSquareOutlined
                className="text-xl"
                onClick={() => setIsCollapse(true)}
                style={{
                  color: '#6eb9ff',
                }}
              />
            )}
          </div>
        ) : null}

        <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
          {formItems}
        </Row>
        <div
          className={`${isCollapse ? 'scale-y-0 h-0' : 'scale-y-100 h-auto'} transition-all origin-top`}
        >
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
                <Button
                  type="primary"
                  block
                  htmlType="submit"
                  loading={isFetching}
                >
                  {t('submit')}
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
                  {t('clear')}
                </Button>
              </Col>
            </Row>
          </Col>
        </Row>
      </Form>
    );
  };

  const $tableProps: TableProps<TData> = {
    dataSource: data?.data,
    pagination: hasPagination
      ? {
          ...pagination,
          total: data?.total,
          onChange: (page, pageSize) => {
            setPagination({
              ...pagination,
              current: page,
              pageSize,
            });
          },
          onShowSizeChange(current, pageSize) {
            setPagination({
              ...pagination,
              current: 1,
              pageSize,
            });
          },
          position: ['bottomCenter'],
        }
      : false,
    columns,
    loading: isFetching,
    size: 'small',
    rowKey: 'id',
    ...tableProps,
  };

  const tableOutterStyle: CSSProperties = {
    overflowX: 'auto',
    maxWidth:
      breakpoint.xs || breakpoint.sm || breakpoint.md
        ? 'calc(100vw - 24px)'
        : 'auto',
  };

  const AntdTable = (props: TableProps<TData>) => {
    return (
      <div
        style={{
          overflowX: 'auto',
          maxWidth:
            breakpoint.xs || breakpoint.sm || breakpoint.md
              ? 'calc(100vw - 24px)'
              : 'auto',
        }}
      >
        <Table {...$tableProps} {...props} />
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
    tableProps: $tableProps,
    filters,
    tableOutterStyle,
    ...others,
  };
}

export default useTable;
