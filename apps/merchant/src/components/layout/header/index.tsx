import { useGetIdentity, useLogout, useSetLocale } from "@refinedev/core";
import { Layout as AntdLayout, Button, Dropdown, Menu, Space, Typography } from "antd";
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
                return "CN 简体中文";
            case "en":
                return "US English";
            case "th":
                return "TH ไทย";
            default:
                return "CN 简体中文";
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
