import React, { useState } from 'react';
import {
  Layout as AntdLayout,
  ConfigProvider,
  Menu,
  Grid,
  Drawer,
  Button,
} from 'antd';
import {
  UnorderedListOutlined,
  LogoutOutlined,
  DashboardOutlined,
  BarsOutlined,
} from '@ant-design/icons';
import {
  useLogout,
  CanAccess,
  useIsExistAuthentication,
  useMenu,
} from '@refinedev/core';
import { Link } from 'react-router';

import { Title as DefaultTitle } from '../title';

import { drawerButtonStyles } from './styles';
import { useTranslation } from 'react-i18next';

// Define ITreeMenu type for compatibility
type ITreeMenu = {
  icon?: React.ReactNode;
  label?: string;
  route?: string;
  name: string;
  children: ITreeMenu[];
  parentName?: string;
};
const { SubMenu } = Menu;

type SiderRenderProps = {
  dashboard: React.ReactNode;
  items: React.ReactNode;
  logout: React.ReactNode;
  collapsed: boolean;
};

type DefaultSiderProps = {
  render?: (props: SiderRenderProps) => React.ReactNode;
};

export const Sider: React.FC<DefaultSiderProps> = ({ render }) => {
  const { t } = useTranslation();
  const [collapsed, setCollapsed] = useState<boolean>(false);
  const [drawerOpen, setDrawerOpen] = useState<boolean>(false);
  const isExistAuthentication = useIsExistAuthentication();
  const { mutate: mutateLogout } = useLogout();
  // const translate = useTranslate();
  const { menuItems, selectedKey, defaultOpenKeys } = useMenu();
  const breakpoint = Grid.useBreakpoint();
  // hasDashboard is removed in v5, set to false
  const hasDashboard = false;

  const isMobile =
    typeof breakpoint.lg === 'undefined' ? false : !breakpoint.lg;

  // useTitle is removed in v5, use DefaultTitle directly
  const RenderToTitle = DefaultTitle;

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
            <SubMenu
              key={route}
              icon={icon ?? <UnorderedListOutlined />}
              title={label}
            >
              {renderTreeView(children, selectedKey)}
            </SubMenu>
          </CanAccess>
        );
      }
      const isSelected = selectedKey.includes(route ?? '');
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
              fontWeight: isSelected ? 'bold' : 'normal',
            }}
            icon={icon ?? (isRoute && <UnorderedListOutlined />)}
          >
            <Link to={route || '/'}>{label}</Link>
            {!collapsed && isSelected && (
              <div className="ant-menu-tree-arrow" />
            )}
          </Menu.Item>
        </CanAccess>
      );
    });
  };

  const logout = isExistAuthentication && (
    <Menu.Item
      key="logout"
      onClick={() => mutateLogout()}
      icon={<LogoutOutlined />}
    >
      {t('logout')}
    </Menu.Item>
  );

  const dashboard = hasDashboard ? (
    <Menu.Item
      key="dashboard"
      style={{
        fontWeight: selectedKey === '/' ? 'bold' : 'normal',
      }}
      icon={<DashboardOutlined />}
    >
      <Link to="/">{t('navigation.home')}</Link>
      {!collapsed && selectedKey === '/' && (
        <div className="ant-menu-tree-arrow" />
      )}
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
    const parseSelectedKey = selectedKey
      .split('/')
      .reduce((prev, cur, index) => {
        if (index === selectedKey.split('/').length - 1) return prev;
        if (cur !== '') return `${prev}/${cur}`;
        else return prev;
      }, '');
    return (
      <Menu
        selectedKeys={[selectedKey, parseSelectedKey]}
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
          bodyStyle={{
            padding: 0,
          }}
          maskClosable={true}
        >
          <AntdLayout>
            <AntdLayout.Sider style={{ height: '100vh', overflow: 'hidden' }}>
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
            colorItemBg: 'transparent',
            colorItemText: '#fff',
            colorItemTextSelected: '#fff',
            colorItemBgSelected: '#1677ff',
            colorItemTextHover: '#fff',
            colorItemBgHover: '#1677ff',
          },
        },
      }}
    >
      {renderContent()}
    </ConfigProvider>
  );
};
