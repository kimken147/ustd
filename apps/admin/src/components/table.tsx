import { Table as AntdTable, Grid } from "antd";
import type { TableProps } from "antd";

function Table<TData extends object = any>(props: TableProps<TData>) {
    const breakpoint = Grid.useBreakpoint();
    return (
        <div
            style={{
                overflowX: "auto",
                maxWidth: breakpoint.xs || breakpoint.sm || breakpoint.md ? "calc(100vw - 24px)" : "auto",
            }}
        >
            <AntdTable {...props} />
        </div>
    );
}

Table.Column = AntdTable.Column;

export default Table;
