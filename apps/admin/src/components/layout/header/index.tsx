import { useGetIdentity, useLogout, useSetLocale } from '@refinedev/core';
import { Layout, Button, Dropdown, Space, Typography } from 'antd';
import type { MenuProps } from 'antd';
import { useTranslation } from 'react-i18next';
import { DownOutlined } from '@ant-design/icons';
import React from 'react';

const { Text } = Typography;

export const Header: React.FC = () => {
  const { data: user } = useGetIdentity<Profile>();
  const { mutate: logout } = useLogout();
  const { t, i18n } = useTranslation();
  const changeLanguage = useSetLocale();

  const getLangText = (lang: string | undefined) => {
    switch (lang) {
      case 'zh-CN':
        return '🇨🇳 简体中文';
      case 'en':
        return '🇺🇸 English';
      default:
        return '🇨🇳 简体中文';
    }
  };

  const langMenuItems: MenuProps['items'] = [...(i18n.options.supportedLngs || [])]
    .filter(lang => lang !== 'cimode')
    .map((lang: string) => ({
      key: lang,
      label: getLangText(lang),
      onClick: () => changeLanguage(lang),
    }));

  return (
    <Layout.Header
      style={{
        display: 'flex',
        justifyContent: 'flex-end',
        alignItems: 'center',
        padding: '0px 24px',
        height: '64px',
        background: 'transparent',
      }}
    >
      <Space size="middle">
        <Dropdown menu={{ items: langMenuItems, selectedKeys: i18n.language ? [i18n.language] : [] }}>
          <Button type="link">
            <Space>
              {getLangText(i18n.language)}
              <DownOutlined />
            </Space>
          </Button>
        </Dropdown>
        {user?.name && (
          <Text ellipsis strong style={{ display: 'flex', alignItems: 'center' }}>
            {user.name || user.username}
          </Text>
        )}
        <Button onClick={() => logout()}>{t('logout')}</Button>
      </Space>
    </Layout.Header>
  );
};
