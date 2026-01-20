import React from 'react';
import { Card, Form, Button, Table, Row, Col } from 'antd';
import type { FormProps, TableProps, FormItemProps } from 'antd';
import { useTranslate } from '@refinedev/core';

export interface ListPageLayoutProps {
  children: React.ReactNode;
}

export interface FilterProps {
  formProps: FormProps;
  children: React.ReactNode;
  loading?: boolean;
}

export interface ListTableProps<T = any> extends TableProps<T> {
  children?: React.ReactNode;
}

// 定義 Filter 元件類型（包含 Item 子元件）
export interface FilterComponent extends React.FC<FilterProps> {
  Item: React.FC<FormItemProps>;
}

// 定義 ListPageLayout 元件類型
export interface ListPageLayoutComponent extends React.FC<ListPageLayoutProps> {
  Filter: FilterComponent;
  Table: <T extends object = any>(props: ListTableProps<T>) => React.ReactElement;
}

/**
 * ListPageLayout - 列表頁面佈局元件
 *
 * 使用 Compound Components 模式，提供 Filter 和 Table 子元件
 *
 * @example
 * ```tsx
 * <ListPageLayout>
 *   <ListPageLayout.Filter formProps={searchFormProps}>
 *     <Col xs={24} md={6}>
 *       <ListPageLayout.Filter.Item name="keyword" label="關鍵字">
 *         <Input />
 *       </ListPageLayout.Filter.Item>
 *     </Col>
 *   </ListPageLayout.Filter>
 *   <ListPageLayout.Table {...tableProps} columns={columns} />
 * </ListPageLayout>
 * ```
 */
function ListPageLayout({ children }: ListPageLayoutProps) {
  return <div className="list-page-layout">{children}</div>;
}

/**
 * FilterItem - 篩選表單項目（封裝 Form.Item）
 */
function FilterItem(props: FormItemProps) {
  return <Form.Item {...props} />;
}

/**
 * Filter - 篩選表單區塊
 */
function Filter({ formProps, children, loading }: FilterProps) {
  const t = useTranslate();
  const [form] = Form.useForm();
  const actualForm = formProps.form || form;

  return (
    <Card className="mb-4">
      <Form {...formProps} form={actualForm} layout="vertical">
        <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
          {children}
        </Row>
        <Row gutter={[{ xs: 8, sm: 8, md: 16 }, 0]} align="middle">
          <Col xs={24} md={6}>
            <Row gutter={8}>
              <Col span={12}>
                <Button
                  type="primary"
                  block
                  htmlType="submit"
                  loading={loading}
                >
                  {t('submit')}
                </Button>
              </Col>
              <Col span={12}>
                <Button
                  block
                  onClick={() => {
                    actualForm.resetFields();
                    actualForm.submit();
                  }}
                >
                  {t('clear')}
                </Button>
              </Col>
            </Row>
          </Col>
        </Row>
      </Form>
    </Card>
  );
}

// 掛載 Item 子元件到 Filter
Filter.Item = FilterItem;

/**
 * ListTable - 表格區塊
 *
 * 使用 Ant Design Table 內建的 RWD 支援:
 * - scroll.x: 'max-content' 啟用水平捲動
 * - 欄位使用 responsive 屬性控制顯示 (如 responsive: ['lg', 'xl'])
 * - 欄位使用 fixed: 'left' | 'right' 固定重要欄位
 */
function ListTable<T extends object = any>({
  children,
  ...tableProps
}: ListTableProps<T>) {
  return (
    <Table<T>
      size="small"
      rowKey="id"
      scroll={{ x: 'max-content' }}
      {...tableProps}
    >
      {children}
    </Table>
  );
}

// 掛載子元件並轉型
const ListPageLayoutWithSubComponents = ListPageLayout as ListPageLayoutComponent;
ListPageLayoutWithSubComponents.Filter = Filter as FilterComponent;
ListPageLayoutWithSubComponents.Table = ListTable;

// 匯出類型
export type { FilterProps as ListPageLayoutFilterProps };
export type { ListTableProps as ListPageLayoutTableProps };

export { ListPageLayoutWithSubComponents as ListPageLayout };
export default ListPageLayoutWithSubComponents;
