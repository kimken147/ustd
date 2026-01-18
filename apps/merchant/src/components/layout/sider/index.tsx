import React, { useState } from "react";
import { Layout as AntdLayout, ConfigProvider, Menu, Grid, Drawer, Button } from "antd";
import {
    UnorderedListOutlined,
    LogoutOutlined,
    DashboardOutlined,
    BarsOutlined,
} from "@ant-design/icons";
import {
    useLogout,
    useTitle,
    CanAccess,
    ITreeMenu,
    useIsExistAuthentication,
    useRouterContext,
    useMenu,
    useRefineContext,
    useTranslate,
} from "@refinedev/core";

import { Title as DefaultTitle } from "../title";

import { drawerButtonStyles } from "./styles";

const { SubMenu } = Menu;

export const Sider: React.FC<{ render?: (props: any) => React.ReactNode }> = ({ render }) => {
    const [collapsed, setCollapsed] = useState<boolean>(false);
    const [drawerOpen, setDrawerOpen] = useState<boolean>(false);
    const isExistAuthentication = useIsExistAuthentication();
    const { Link } = useRouterContext();
    const { mutate: mutateLogout } = useLogout();
    const Title = useTitle();
    const translate = useTranslate();
    const { menuItems, selectedKey, defaultOpenKeys } = useMenu();
    const breakpoint = Grid.useBreakpoint();
    const { hasDashboard } = useRefineContext();

    const isMobile = typeof breakpoint.lg === "undefined" ? false : !breakpoint.lg;

    const RenderToTitle = Title ?? DefaultTitle;

    const renderTreeView = (tree: ITreeMenu[], selectedKey: string) => {
        return tree.map((item: ITreeMenu) => {
            const { icon, label, route, name, children, parentName } = item;

            if (children.length > 0) {
                return (
                    <CanAccess
                        key={route}
                        resource={name.toLowerCase()}
                        action="list"
                        params={{
                            resource: item,
                        }}
                    >
                        <SubMenu key={route} icon={icon ?? <UnorderedListOutlined />} title={label}>
                            {renderTreeView(children, selectedKey)}
                        </SubMenu>
                    </CanAccess>
                );
            }
            const isSelected = selectedKey.includes(route ?? "");
            const isRoute = !(parentName !== undefined && children.length === 0);
            return (
                <CanAccess
                    key={route}
                    resource={name.toLowerCase()}
                    action="list"
                    params={{
                        resource: item,
                    }}
                >
                    <Menu.Item
                        key={route}
                        style={{
                            fontWeight: isSelected ? "bold" : "normal",
                        }}
                        icon={icon ?? (isRoute && <UnorderedListOutlined />)}
                    >
                        <Link to={route}>{label}</Link>
                        {!collapsed && isSelected && <div className="ant-menu-tree-arrow" />}
                    </Menu.Item>
                </CanAccess>
            );
        });
    };

    const logout = isExistAuthentication && (
        <Menu.Item key="logout" onClick={() => mutateLogout()} icon={<LogoutOutlined />}>
            {translate("logout")}
        </Menu.Item>
    );

    const dashboard = hasDashboard ? (
        <Menu.Item
            key="dashboard"
            style={{
                fontWeight: selectedKey === "/" ? "bold" : "normal",
            }}
            icon={<DashboardOutlined />}
        >
            <Link to="/">{translate("home.title")}</Link>
            {!collapsed && selectedKey === "/" && <div className="ant-menu-tree-arrow" />}
        </Menu.Item>
    ) : null;

    const items = renderTreeView(menuItems, selectedKey);

    const renderSider = () => {
        if (render) {
            return render({
                dashboard,
                items,
                logout,
                collapsed,
            });
        }
        return (
            <>
                {dashboard}
                {items}
                {logout}
            </>
        );
    };

    const renderMenu = () => {
        return (
            <Menu
                selectedKeys={[selectedKey, `/${selectedKey.split("/")[1]}`]}
                defaultOpenKeys={defaultOpenKeys}
                mode="inline"
                onClick={() => {
                    setDrawerOpen(false);
                    if (!breakpoint.lg) {
                        setCollapsed(true);
                    }
                }}
            >
                {renderSider()}
            </Menu>
        );
    };

    const renderDrawerSider = () => {
        return (
            <>
                <Drawer
                    open={drawerOpen}
                    onClose={() => setDrawerOpen(false)}
                    placement="left"
                    closable={false}
                    width={200}
                    styles={{
                        body: {
                            padding: 0,
                        },
                    }}
                    maskClosable={true}
                >
                    <AntdLayout>
                        <AntdLayout.Sider style={{ height: "100vh", overflow: "hidden" }}>
                            <RenderToTitle collapsed={false} />
                            {renderMenu()}
                        </AntdLayout.Sider>
                    </AntdLayout>
                </Drawer>
                <Button
                    style={drawerButtonStyles}
                    size="large"
                    onClick={() => setDrawerOpen(true)}
                    icon={<BarsOutlined />}
                ></Button>
            </>
        );
    };

    const renderContent = () => {
        if (isMobile) {
            return renderDrawerSider();
        }

        return (
            <AntdLayout.Sider
                collapsible
                collapsed={collapsed}
                onCollapse={(collapsed: boolean): void => setCollapsed(collapsed)}
                collapsedWidth={80}
                breakpoint="lg"
            >
                <RenderToTitle collapsed={collapsed} />
                {renderMenu()}
            </AntdLayout.Sider>
        );
    };

    return (
        <ConfigProvider
            theme={{
                components: {
                    Menu: {
                        colorItemBg: "transparent",
                        colorItemText: "#fff",
                        colorItemTextSelected: "#fff",
                        colorItemBgSelected: "#1677ff",
                        colorItemTextHover: "#fff",
                        colorItemBgHover: "#1677ff",
                    },
                },
            }}
        >
            {renderContent()}
        </ConfigProvider>
    );
};
