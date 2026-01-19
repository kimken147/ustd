import './index.sass';
import { Refine, Authenticated } from '@refinedev/core';
import {
  useNotificationProvider,
  ErrorComponent,
  ThemedLayout,
  ThemedTitle,
} from '@refinedev/antd';
import '@refinedev/antd/dist/reset.css';
import routerProvider from '@refinedev/react-router';
import { BrowserRouter, Routes, Route, Outlet } from 'react-router';
import { ConfigProvider, App as AntdApp } from 'antd';
import { authProvider } from './authProvider';
import AuthPage from 'components/authPage';
import HomePage from 'pages/home';
import {
  ForkOutlined,
  HomeOutlined,
  LockOutlined,
  MoneyCollectOutlined,
  SwapOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { apiUrl } from 'index';

import customDataProvider from 'dataProvider';
import dataProvider from '@refinedev/simple-rest';
import antdCNLocale from 'antd/locale/zh_CN';
import antdENLocale from 'antd/locale/en_US';
import antdTHLocale from 'antd/locale/th_TH';
import CollectionList from 'pages/collection/list';
import CollectionShow from 'pages/collection/show';
import PayForAnotherList from 'pages/PayForAnother/list';
import PayForAnotherCreate from 'pages/PayForAnother/create';
import WithdrawCreate from 'pages/PayForAnother/createWithdraw';
import BankCardList from 'pages/bankCard/list';
import BankCardCreate from 'pages/bankCard/create';
import WalletHistoryList from 'pages/wallet-history';
import MemberList from 'pages/member/list';
import MemberCreate from 'pages/member/create';
import MemberShow from 'pages/member/show';
import { Helmet } from 'react-helmet';
import { initDayjs } from '@morgan-ustd/shared';
import accessControlProvider from 'accessControlProvider';
import SubAccountList from 'pages/subAccount/list';
import SubAccountCreate from 'pages/subAccount/create';
import SubAccountShow from 'pages/subAccount/show';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import dayjs from 'dayjs';
import 'dayjs/locale/zh-cn';
import 'dayjs/locale/th';

initDayjs();

function App() {
  const { t, i18n } = useTranslation();
  const [currentLocale, setCurrentLocale] = useState(i18n.language || 'zh-CN');

  const i18nProvider = {
    translate: (key: string, options?: any) => {
      const result = t(key, options);
      if (typeof result === 'string') return result;
      if (typeof result === 'object' && result !== null && 'toString' in result) {
        try {
          return result.toString();
        } catch {
          return JSON.stringify(result);
        }
      }
      return String(result);
    },
    changeLocale: (lang: string) => {
      setCurrentLocale(lang);
      const dayjsLocaleMap: Record<string, string> = {
        'zh-CN': 'zh-cn',
        en: 'en',
        th: 'th',
      };
      dayjs.locale(dayjsLocaleMap[lang] || 'zh-cn');
      return i18n.changeLanguage(lang);
    },
    getLocale: () => i18n.language,
  };

  const getAntdLocale = () => {
    switch (currentLocale) {
      case 'zh-CN':
        return antdCNLocale;
      case 'th':
        return antdTHLocale;
      default:
        return antdENLocale;
    }
  };

  const resources = [
    {
      name: 'home',
      list: '/',
      meta: { label: t('home.title'), icon: <HomeOutlined /> },
    },
    {
      name: 'transactions',
      list: '/transactions',
      show: '/transactions/:id',
      meta: { label: t('collection.titles.main'), icon: <SwapOutlined /> },
    },
    {
      name: 'withdraws',
      list: '/withdraws',
      create: '/withdraws/create',
      meta: { label: t('withdraw.titles.main'), icon: <MoneyCollectOutlined /> },
    },
    {
      name: 'pay-for-another',
      list: '/pay-for-another',
      meta: { label: '建立下发', parent: 'withdraws', hide: true },
    },
    {
      name: 'bank-cards',
      list: '/bank-cards',
      create: '/bank-cards/create',
      meta: { label: '下发银行卡', parent: 'withdraws', hide: true },
    },
    {
      name: 'wallet-histories',
      list: '/wallet-histories',
      meta: { label: t('walletHistory.titles.list'), icon: <WalletOutlined /> },
    },
    {
      name: 'members',
      list: '/members',
      create: '/members/create',
      show: '/members/:id',
      meta: { label: '下级管理', icon: <ForkOutlined className="rotate-180" /> },
    },
    {
      name: 'sub-accounts',
      list: '/sub-accounts',
      create: '/sub-accounts/create',
      show: '/sub-accounts/:id',
      meta: { label: t('subAccount.titles.list'), icon: <LockOutlined /> },
    },
  ];

  return (
    <BrowserRouter>
      <Helmet>
        <link rel="icon" href={process.env.REACT_APP_FAVICON_SRC} sizes="16x16" />
      </Helmet>
      <ConfigProvider
        form={{
          validateMessages: {
            required: '请填入必要资讯',
          },
        }}
        locale={getAntdLocale()}
        theme={{
          components: {
            Layout: {
              colorBgHeader: '#0090e5',
              colorBgTrigger: '#0090e5',
            },
          },
          token: {
            colorPrimary: '#0090e5',
            colorPrimaryHover: ' #1677ff',
            colorPrimaryActive: ' #1677ff',
          },
        }}
      >
        <AntdApp>
          <Refine
            dataProvider={{
              default: customDataProvider(apiUrl),
              test: dataProvider('https://api.fake-rest.refine.dev'),
            }}
            accessControlProvider={accessControlProvider}
            notificationProvider={useNotificationProvider}
            routerProvider={routerProvider}
            authProvider={authProvider}
            i18nProvider={i18nProvider}
            options={{
              reactQuery: {
                clientConfig: {
                  defaultOptions: {
                    queries: {
                      retry: false,
                    },
                  },
                },
              },
              syncWithLocation: true,
              warnWhenUnsavedChanges: true,
            }}
            resources={resources}
          >
            <Routes>
              {/* Authenticated routes */}
              <Route
                element={
                  <Authenticated key="authenticated" fallback={<AuthPage />}>
                    <ThemedLayout
                      Title={({ collapsed }) => (
                        <ThemedTitle
                          collapsed={collapsed}
                          text={process.env.REACT_APP_TITLE || 'Merchant'}
                          icon={
                            collapsed ? (
                              process.env.REACT_APP_LOGO_MINI_SRC ? (
                                <img
                                  src={process.env.REACT_APP_LOGO_MINI_SRC}
                                  alt="Logo"
                                  style={{ maxHeight: '24px' }}
                                />
                              ) : null
                            ) : process.env.REACT_APP_LOGO_SRC ? (
                              <img
                                src={process.env.REACT_APP_LOGO_SRC}
                                alt="Logo"
                                style={{ maxHeight: '24px' }}
                              />
                            ) : null
                          }
                        />
                      )}
                    >
                      <Outlet />
                    </ThemedLayout>
                  </Authenticated>
                }
              >
                {/* Home */}
                <Route index element={<HomePage />} />

                {/* Transactions (Collection) */}
                <Route path="/transactions" element={<CollectionList />} />
                <Route path="/transactions/:id" element={<CollectionShow />} />

                {/* Withdraws */}
                <Route path="/withdraws" element={<PayForAnotherList />} />
                <Route path="/withdraws/create" element={<PayForAnotherCreate />} />
                <Route path="/pay-for-another" element={<WithdrawCreate />} />

                {/* Bank Cards */}
                <Route path="/bank-cards" element={<BankCardList />} />
                <Route path="/bank-cards/create" element={<BankCardCreate />} />

                {/* Wallet History */}
                <Route path="/wallet-histories" element={<WalletHistoryList />} />

                {/* Members */}
                <Route path="/members" element={<MemberList />} />
                <Route path="/members/create" element={<MemberCreate />} />
                <Route path="/members/:id" element={<MemberShow />} />

                {/* Sub Accounts */}
                <Route path="/sub-accounts" element={<SubAccountList />} />
                <Route path="/sub-accounts/create" element={<SubAccountCreate />} />
                <Route path="/sub-accounts/:id" element={<SubAccountShow />} />

                {/* Catch all */}
                <Route path="*" element={<ErrorComponent />} />
              </Route>

              {/* Login page */}
              <Route path="/login" element={<AuthPage />} />
            </Routes>
          </Refine>
        </AntdApp>
      </ConfigProvider>
    </BrowserRouter>
  );
}

export default App;
