import "./index.sass";
import { I18nProvider, Refine } from "@pankod/refine-core";
import { notificationProvider, ReadyPage, ErrorComponent, ConfigProvider } from "@pankod/refine-antd";
import "@pankod/refine-antd/dist/reset.css";
import routerProvider from "@pankod/refine-react-router-v6";
import { Title, Header, Sider, Footer, Layout, OffLayoutArea } from "components/layout";
import { authProvider } from "./authProvider";
import AuthPage from "components/authPage";
import HomePage from "pages/home";
import {
    ForkOutlined,
    HomeOutlined,
    LockOutlined,
    MoneyCollectOutlined,
    SwapOutlined,
    WalletOutlined,
} from "@ant-design/icons";
import { apiUrl } from "index";

import customDataProvider from "dataProvider";
import dataProvider from "@pankod/refine-simple-rest";
import antdCNLocale from "antd/es/locale/zh_CN";
import antdENLocale from "antd/es/locale/en_US";
import antdTHLocale from "antd/es/locale/th_TH";
import CollectionList from "pages/collection/list";
import CollectionShow from "pages/collection/show";
import PayForAnotherList from "pages/PayForAnother/list";
import PayForAnotherCreate from "pages/PayForAnother/create";
import WithdrawCreate from "pages/PayForAnother/createWithdraw";
import BankCardList from "pages/bankCard/list";
import BankCardCreate from "pages/bankCard/create";
import WalletHistoryList from "pages/wallet-history";
import MemberList from "pages/member/list";
import MemberCreate from "pages/member/create";
import MemberShow from "pages/member/show";
import { Helmet } from "react-helmet";
import { initDayjs } from "@morgan-ustd/shared";
import accessControlProvider from "accessControlProvider";
import SubAccountList from "pages/subAccount/list";
import SubAccountCreate from "pages/subAccount/create";
import SubAccountShow from "pages/subAccount/show";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import dayjs from "dayjs";
import "dayjs/locale/zh-cn";
import "dayjs/locale/th";

initDayjs();

function App() {
    const { t, i18n } = useTranslation();
    const [currentLocale, setCurrentLocale] = useState(i18n.language || "zh-CN");

    const i18nProvider: I18nProvider = {
        translate: (key: string, options?: any) => t(key, options) as string,
        changeLocale: (lang: string) => {
            setCurrentLocale(lang);
            const dayjsLocaleMap: Record<string, string> = {
                "zh-CN": "zh-cn",
                en: "en",
                th: "th",
            };
            dayjs.locale(dayjsLocaleMap[lang] || "zh-cn");
            return i18n.changeLanguage(lang);
        },
        getLocale: () => i18n.language,
    };

    const getAntdLocale = () => {
        switch (currentLocale) {
            case "zh-CN":
                return antdCNLocale;
            case "th":
                return antdTHLocale;
            default:
                return antdENLocale;
        }
    };

    return (
        <>
            <Helmet>
                <link rel="icon" href={process.env.REACT_APP_FAVICON_SRC} sizes="16x16" />
            </Helmet>
            <ConfigProvider
                form={{
                    validateMessages: {
                        required: "请填入必要资讯",
                    },
                }}
                locale={getAntdLocale()}
                theme={{
                    components: {
                        Layout: {
                            colorBgHeader: "#0090e5",
                            colorBgTrigger: "#0090e5",
                        },
                    },
                    token: {
                        colorPrimary: "#0090e5",
                        colorPrimaryHover: " #1677ff",
                        colorPrimaryActive: " #1677ff",
                    },
                }}
            >
                <Refine
                    dataProvider={{
                        default: customDataProvider(apiUrl),
                        test: dataProvider("https://api.fake-rest.refine.dev"),
                    }}
                    accessControlProvider={accessControlProvider}
                    notificationProvider={notificationProvider}
                    routerProvider={routerProvider}
                    ReadyPage={ReadyPage}
                    catchAll={<ErrorComponent />}
                    Title={Title}
                    Header={Header}
                    Sider={Sider}
                    Footer={Footer}
                    Layout={Layout}
                    OffLayoutArea={OffLayoutArea}
                    authProvider={authProvider}
                    i18nProvider={i18nProvider}
                    LoginPage={AuthPage}
                    options={{
                        breadcrumb: false,
                        reactQuery: {
                            devtoolConfig: false,
                            clientConfig: {
                                defaultOptions: {
                                    queries: {
                                        retry: false,
                                    },
                                },
                            },
                        },
                    }}
                    resources={[
                        {
                            name: "home",
                            list: HomePage,
                            icon: <HomeOutlined />,
                            options: {
                                label: t("home.title"),
                            },
                        },
                        {
                            name: "transactions",
                            list: CollectionList,
                            icon: <SwapOutlined />,
                            options: {
                                label: t("collection.titles.main"),
                            },
                            show: CollectionShow,
                        },
                        {
                            name: "withdraws",
                            list: PayForAnotherList,
                            icon: <MoneyCollectOutlined />,
                            options: {
                                label: t("withdraw.titles.main"),
                            },
                            create: PayForAnotherCreate,
                        },
                        {
                            name: "pay-for-another",
                            list: WithdrawCreate,
                            options: {
                                label: "建立下发",
                                hide: true,
                            },
                            parentName: "withdraws",
                        },
                        {
                            name: "bank-cards",
                            list: BankCardList,
                            create: BankCardCreate,
                            options: {
                                label: "下发银行卡",
                                hide: true,
                            },
                            parentName: "withdraws",
                        },
                        {
                            name: "wallet-histories",
                            list: WalletHistoryList,
                            options: {
                                label: t("walletHistory.titles.list"),
                            },
                            icon: <WalletOutlined />,
                        },
                        {
                            name: "members",
                            list: MemberList,
                            options: {
                                label: "下级管理",
                            },
                            icon: <ForkOutlined className="rotate-180" />,
                            create: MemberCreate,
                            show: MemberShow,
                        },
                        {
                            name: "sub-accounts",
                            options: {
                                label: t("subAccount.titles.list"),
                            },
                            icon: <LockOutlined />,
                            list: SubAccountList,
                            create: SubAccountCreate,
                            show: SubAccountShow,
                        },
                    ]}
                />
            </ConfigProvider>
        </>
    );
}

export default App;
