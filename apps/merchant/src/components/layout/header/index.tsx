import { useGetIdentity, useLogout, useSetLocale } from "@pankod/refine-core";
import { AntdLayout, Button, Dropdown, Menu, Space, Typography } from "@pankod/refine-antd";
import { useTranslation } from "react-i18next";
import { DownOutlined } from "@ant-design/icons";
import React from "react";

const { Text } = Typography;

export const Header: React.FC = () => {
    const { data: user } = useGetIdentity<Profile>();
    const { mutate: logout } = useLogout();

    const { t, i18n } = useTranslation();
    const changeLanguage = useSetLocale();

    const getLangText = (lang: string | undefined) => {
        switch (lang) {
            case "zh-CN":
                return "🇨🇳 简体中文";
            case "en":
                return "🇺🇸 English"; // 或 🇬🇧 for 英國
            case "th":
                return "🇹🇭 ไทย"; // 泰文
            default:
                return "🇨🇳 简体中文";
        }
    };

    const menu = (
        <Menu selectedKeys={i18n.language ? [i18n.language] : []}>
            {[...(i18n.options.supportedLngs || [])]
                .filter((lang) => lang !== "cimode")
                .map((lang: string) => (
                    <Menu.Item
                        key={lang}
                        onClick={() => {
                            changeLanguage(lang);
                        }}
                    >
                        {getLangText(lang)}
                    </Menu.Item>
                ))}
        </Menu>
    );

    return (
        <AntdLayout.Header
            style={{
                display: "flex",
                justifyContent: "flex-end",
                alignItems: "center",
                padding: "0px 24px",
                height: "64px",
                color: "#000",
                background: "transparent",
            }}
        >
            <Dropdown overlay={menu}>
                <Button type="link">
                    <Space>
                        {getLangText(i18n.language)}
                        <DownOutlined />
                    </Space>
                </Button>
            </Dropdown>
            <Space style={{ marginLeft: "8px" }} size="middle">
                {user?.name && (
                    <Text ellipsis strong style={{ color: "#000", display: "flex", alignItems: "center" }}>
                        {user.name || user.username}
                    </Text>
                )}
                <Button onClick={() => logout()}>{t("logout")}</Button>
            </Space>
        </AntdLayout.Header>
    );
};
