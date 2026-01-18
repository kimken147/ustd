import { TableProps, Table as AntdTable, Grid } from "@pankod/refine-antd";

const Table: <TData extends object = any>(props: TableProps<TData>) => React.ReactElement<TableProps<TData>> = (
    props,
) => {
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
};

export default Table;
