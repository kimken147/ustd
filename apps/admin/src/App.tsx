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
import { ConfigProvider, App as AntdApp, theme } from 'antd';
import { authProvider } from './authProvider';
import AuthPage from 'components/authPage';
import HomePage from 'pages/home';
import {
  BarChartOutlined,
  DeploymentUnitOutlined,
  FieldTimeOutlined,
  HomeOutlined,
  LockOutlined,
  QrcodeOutlined,
  SettingOutlined,
  ShopOutlined,
  SwapOutlined,
  TagOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { apiUrl } from 'index';

import UserChannelAccountList from 'pages/userChannel/list';
import UserChannelShow from 'pages/userChannel/show';
import UserChannelCreate from 'pages/userChannel/create/create';
import customDataProvider from 'dataProvider';
import dataProvider from '@refinedev/simple-rest';
import ProviderList from 'pages/provider/list';
import ProviderCreate from 'pages/provider/create';
import MerchantList from 'pages/merchant/list';
import MerchantCreate from 'pages/merchant/create';
import MerchantWhiteList from 'pages/merchant/whiteList';
import MerchantBannedList from 'pages/merchant/bannedList';
import MerchantApiWhiteList from 'pages/merchant/apiWhiteList';
import MerchantShow from 'pages/merchant/show';
import ChannelList from 'pages/channel/list';
import SystemSettingList from 'pages/systemSetting/list';
import LiveList from 'pages/live/list';
import CollectionList from 'pages/transaction/collection/list';
import PayForAnotherList from 'pages/transaction/PayForAnother/list';
import FinanceStatisticPage from 'pages/financeStatitic/list';
import MerchantWalletList from 'pages/merchant/wallet-history/list';
import CollectionShow from 'pages/transaction/collection/show';
import PayForAnotherShow from 'pages/transaction/PayForAnother/show';
import UserWalletHistoryList from 'pages/merchant/user-wallet-history/list';
import UserBankCardList from 'pages/userBankCard/list';
import PermissionList from 'pages/permissions/list';
import SubAccountCreate from 'pages/permissions/create';
import SubAccountShow from 'pages/permissions/show';
import accessControlProvider from 'accessControlProvider';
import LoginWhiteList from 'pages/loginWhiteList/list';
import ChildWithdrawCreate from 'pages/transaction/PayForAnother/childWithdraw/create';
import TransactionGroupList from 'pages/provider/transaction/list';
import DepositGroupList from 'pages/provider/deposit/list';
import { Helmet } from 'react-helmet';
import { initDayjs } from '@morgan-ustd/shared';
import { AppModeProvider, useAppMode, useTerminology } from 'contexts/AppModeContext';
import TransitionDemoCreate from 'pages/transaction/collection/create';
import ThirdChannelList from 'pages/thirdChannel/list';
import ThirdChannelSettingList from 'pages/thirdChannel/setting/list';
import BankList from 'pages/systemSetting/bank/list';
import TagList from 'pages/tag/list';
import TagCreate from 'pages/tag/create';
import FundList from 'pages/transaction/fund/list';
import FundCreate from 'pages/transaction/fund/create';
import ChainTransactionList from 'pages/chainTransaction/list';
import DepositList from 'pages/transaction/deposit/list';
import ProvidersList from 'pages/providers/list';
import ProvidersCreate from 'pages/providers/create';
import ProviderShow from 'pages/providers/show';
import ProviderWalletList from 'pages/providers/wallet-history/list';
import ProviderUserWalletHistoryList from 'pages/providers/user-wallet-history/list';
import ProviderWhiteList from 'pages/providers/whiteList/list';
import FundManagementBatchTransfer from 'pages/fundManagement';

import { useTranslation } from 'react-i18next';
import './i18n';

import enUS from 'antd/locale/en_US';
import zhCN from 'antd/locale/zh_CN';
import { useState } from 'react';
import dayjs from 'dayjs';
import { Header } from 'components/layout/header';
import { WithdrawNotificationListener } from 'components/WithdrawNotificationListener';

import 'dayjs/locale/th';
import 'dayjs/locale/en';
import 'dayjs/locale/zh-cn';

initDayjs();

function App() {
  return (
    <BrowserRouter>
      <AppModeProvider>
        <AppContent />
      </AppModeProvider>
    </BrowserRouter>
  );
}

function AppContent() {
  const { t, i18n } = useTranslation();
  const { isPaufen } = useAppMode();
  const { providerManagement } = useTerminology();
  const [currentLocale, setCurrentLocale] = useState(i18n.language);



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
      };
      dayjs.locale(dayjsLocaleMap[lang] || 'zh-cn');
      return i18n.changeLanguage(lang);
    },
    getLocale: () => i18n.language,
  };

  const getAntdLocale = () => {
    switch (currentLocale) {
      case 'zh-CN':
        return zhCN;
      case 'en':
        return enUS;
      default:
        return zhCN;
    }
  };

  const resources = [
    {
      name: 'home',
      list: '/',
      meta: { label: t('navigation.home'), icon: <HomeOutlined /> },
    },
    {
      name: 'tags',
      list: '/tags',
      create: '/tags/create',
      meta: { label: t('navigation.tags'), icon: <TagOutlined /> },
    },
    {
      name: 'merchants',
      list: '/merchants',
      create: '/merchants/create',
      show: '/merchants/:id',
      meta: { label: t('navigation.merchantManagement'), icon: <ShopOutlined /> },
    },
    {
      name: 'merchants/white-list',
      list: '/merchants/white-list',
      meta: { label: t('navigation.whiteList'), parent: 'merchants', hide: true },
    },
    {
      name: 'merchants/api-white-list',
      list: '/merchants/api-white-list',
      meta: { label: t('navigation.apiWhiteList'), parent: 'merchants', hide: true },
    },
    {
      name: 'merchants/banned-list',
      list: '/merchants/banned-list',
      meta: { label: t('navigation.bannedList'), parent: 'merchants', hide: true },
    },
    {
      name: 'merchants/user-wallet-history',
      list: '/merchants/user-wallet-history',
      meta: { label: t('navigation.merchantWalletHistory'), parent: 'merchants', hide: true },
    },
    {
      name: 'merchants/wallet-histories',
      list: '/merchants/wallet-histories',
      meta: { label: t('navigation.merchantBalanceAdjustment'), parent: 'merchants', hide: true },
    },
    {
      name: 'user-channel-accounts',
      list: '/user-channel-accounts',
      show: '/user-channel-accounts/:id',
      create: '/user-channel-accounts/create',
      meta: { label: t('navigation.paymentAccountManagement'), icon: <WalletOutlined /> },
    },
    {
      name: 'chain-transactions',
      list: '/chain-transactions',
      meta: { label: t('navigation.chainTransactions'), icon: <SwapOutlined /> },
    },
    {
      name: 'transaction',
      meta: { label: t('navigation.transactionManagement'), icon: <SwapOutlined /> },
    },
    {
      name: 'transactions',
      list: '/transactions',
      show: '/transactions/:id',
      create: '/transactions/create',
      meta: { label: t('navigation.collection'), parent: 'transaction' },
    },
    {
      name: 'withdraws',
      list: '/withdraws',
      show: '/withdraws/:id',
      meta: { label: t('navigation.payment'), parent: 'transaction' },
    },
    {
      name: 'internal-transfers',
      list: '/internal-transfers',
      create: '/internal-transfers/create',
      meta: { label: t('navigation.fundManagement'), parent: 'transaction' },
    },
    {
      name: 'provider-deposits',
      list: '/provider-deposits',
      meta: { label: t('navigation.providerDeposit'), parent: 'transaction', hide: !isPaufen },
    },
    {
      name: 'child-withdraws',
      show: '/child-withdraws/:id',
      meta: { label: t('navigation.childWithdrawSplit'), hide: true },
    },
    {
      name: 'user-bank-cards',
      list: '/user-bank-cards',
      meta: { label: t('navigation.merchantBankCardList'), hide: true },
    },
    {
      name: 'online-ready-for-matching-users',
      list: '/online-ready-for-matching-users',
      meta: { label: t('navigation.liveStatus'), icon: <FieldTimeOutlined /> },
    },
    {
      name: 'statistics/v1',
      list: '/finance-statistics',
      meta: { label: t('navigation.financeReport'), icon: <BarChartOutlined /> },
    },
    {
      name: 'providers',
      list: '/providers',
      create: '/providers/create',
      show: '/providers/:id',
      meta: { label: providerManagement, icon: <QrcodeOutlined /> },
    },
    {
      name: 'providers/merchant-transaction-groups',
      list: '/providers/merchant-transaction-groups',
      meta: { label: t('navigation.collectionLine'), parent: 'providers', hide: true },
    },
    {
      name: 'providers/merchant-matching-deposit-groups',
      list: '/providers/merchant-matching-deposit-groups',
      meta: { label: t('navigation.collectionLine'), parent: 'providers', hide: true },
    },
    {
      name: 'providers/white-list',
      list: '/providers/white-list',
      meta: { label: t('navigation.whiteList'), parent: 'providers', hide: true },
    },
    {
      name: 'providers/wallet-histories',
      list: '/providers/wallet-histories',
      meta: { label: t('navigation.providerBalanceAdjustment'), parent: 'providers', hide: true },
    },
    {
      name: 'providers/user-wallet-history',
      list: '/providers/user-wallet-history',
      meta: { label: t('navigation.providerWalletHistory'), parent: 'providers', hide: true },
    },
    {
      name: 'channels',
      list: '/channels',
      meta: { label: t('navigation.channelManagement'), icon: <DeploymentUnitOutlined /> },
    },
    {
      name: 'thirdchannel',
      list: '/thirdchannel',
      meta: { label: t('navigation.thirdPartyManagement') },
    },
    {
      name: 'thirdchannel/merchant-third-channel',
      list: '/thirdchannel/merchant-third-channel',
      meta: { parent: 'thirdchannel', hide: true },
    },
    {
      name: 'feature-toggles',
      list: '/feature-toggles',
      meta: { label: t('navigation.systemSettings'), icon: <SettingOutlined /> },
    },
    {
      name: 'feature-toggles/banks',
      list: '/feature-toggles/banks',
      meta: { label: t('navigation.supportedBanks'), parent: 'feature-toggles', hide: true },
    },
    {
      name: 'sub-accounts',
      list: '/sub-accounts',
      create: '/sub-accounts/create',
      show: '/sub-accounts/:id',
      meta: { label: t('navigation.permissionManagement'), icon: <LockOutlined /> },
    },
    {
      name: 'sub-accounts/login-white-list',
      list: '/sub-accounts/login-white-list',
      meta: { label: t('navigation.adminLoginWhiteList'), parent: 'sub-accounts', hide: true },
    },
  ];

  return (
    <>
      <Helmet>
        <link rel="icon" href={process.env.REACT_APP_FAVICON_SRC} sizes="16x16" />
      </Helmet>
      <ConfigProvider
        form={{
          validateMessages: {
            required: t('validation.required'),
          },
        }}
        locale={getAntdLocale()}
        theme={{
          algorithm: theme.defaultAlgorithm,
          components: {
            Layout: {
              colorBgHeader: '#0090e5',
              colorBgTrigger: '#0090e5',
            },
          },
          token: {
            colorPrimary: '#0090e5',
            colorPrimaryHover: '#1677ff',
            colorPrimaryActive: '#1677ff',
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
            <WithdrawNotificationListener />
            <Routes>
              {/* Authenticated routes */}
              <Route
                element={
                  <Authenticated key="authenticated" fallback={<AuthPage />}>
                    <ThemedLayout
                      Header={() => (
                        <Header />
                      )}
                      Title={({ collapsed }) => (
                        <ThemedTitle
                          collapsed={collapsed}
                          text={
                            <span style={{ fontFamily: "'Ubuntu', sans-serif", fontWeight: 700, fontSize: '1.4rem' }}>
                              {process.env.REACT_APP_TITLE || 'Admin'}
                            </span>
                          }
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

                {/* Tags */}
                <Route path="/tags" element={<TagList />} />
                <Route path="/tags/create" element={<TagCreate />} />

                {/* Merchants */}
                <Route path="/merchants" element={<MerchantList />} />
                <Route path="/merchants/create" element={<MerchantCreate />} />
                <Route path="/merchants/:id" element={<MerchantShow />} />
                <Route path="/merchants/show/:id" element={<MerchantShow />} />
                <Route path="/merchants/white-list" element={<MerchantWhiteList />} />
                <Route path="/merchants/api-white-list" element={<MerchantApiWhiteList />} />
                <Route path="/merchants/banned-list" element={<MerchantBannedList />} />
                <Route path="/merchants/user-wallet-history" element={<UserWalletHistoryList />} />
                <Route path="/merchants/wallet-histories" element={<MerchantWalletList />} />

                {/* User Channel Accounts */}
                <Route path="/user-channel-accounts" element={<UserChannelAccountList />} />
                <Route path="/user-channel-accounts/create" element={<UserChannelCreate />} />
                <Route path="/user-channel-accounts/:id" element={<UserChannelShow />} />

                {/* Chain Transactions */}
                <Route path="/chain-transactions" element={<ChainTransactionList />} />

                {/* Transactions */}
                <Route path="/transactions" element={<CollectionList />} />
                <Route path="/transactions/create" element={<TransitionDemoCreate />} />
                <Route path="/transactions/:id" element={<CollectionShow />} />

                {/* Withdraws */}
                <Route path="/withdraws" element={<PayForAnotherList />} />
                <Route path="/withdraws/:id" element={<PayForAnotherShow />} />
                <Route path="/child-withdraws/:id" element={<ChildWithdrawCreate />} />

                {/* Internal Transfers (Fund Management) */}
                <Route path="/internal-transfers" element={<FundList />} />
                <Route path="/internal-transfers/create" element={<FundCreate />} />
                <Route path="/internal-transfers/batch-transfer" element={<FundManagementBatchTransfer />} />

                {/* User Bank Cards */}
                <Route path="/user-bank-cards" element={<UserBankCardList />} />

                {/* Live Status */}
                <Route path="/online-ready-for-matching-users" element={<LiveList />} />

                {/* Finance Statistics */}
                <Route path="/finance-statistics" element={<FinanceStatisticPage />} />

                {/* Providers / 碼商管理 */}
                <Route path="/providers" element={isPaufen ? <ProvidersList /> : <ProviderList />} />
                <Route path="/providers/create" element={isPaufen ? <ProvidersCreate /> : <ProviderCreate />} />
                <Route path="/providers/:id" element={<ProviderShow />} />
                <Route path="/providers/white-list" element={<ProviderWhiteList />} />
                <Route path="/providers/wallet-histories" element={<ProviderWalletList />} />
                <Route path="/providers/user-wallet-history" element={<ProviderUserWalletHistoryList />} />
                <Route
                  path="/providers/merchant-transaction-groups"
                  element={<TransactionGroupList />}
                />
                <Route
                  path="/providers/merchant-matching-deposit-groups"
                  element={<DepositGroupList />}
                />

                {/* Provider Deposits */}
                <Route path="/provider-deposits" element={<DepositList />} />

                {/* Channels */}
                <Route path="/channels" element={<ChannelList />} />

                {/* Third Channel */}
                <Route path="/thirdchannel" element={<ThirdChannelList />} />
                <Route
                  path="/thirdchannel/merchant-third-channel"
                  element={<ThirdChannelSettingList />}
                />

                {/* System Settings */}
                <Route path="/feature-toggles" element={<SystemSettingList />} />
                <Route path="/feature-toggles/banks" element={<BankList />} />

                {/* Sub Accounts (Permissions) */}
                <Route path="/sub-accounts" element={<PermissionList />} />
                <Route path="/sub-accounts/create" element={<SubAccountCreate />} />
                <Route path="/sub-accounts/:id" element={<SubAccountShow />} />
                <Route path="/sub-accounts/login-white-list" element={<LoginWhiteList />} />

                {/* Catch all */}
                <Route path="*" element={<ErrorComponent />} />
              </Route>

              {/* Login page */}
              <Route path="/login" element={<AuthPage />} />
            </Routes>
          </Refine>
        </AntdApp>
      </ConfigProvider>
    </>
  );
}

export default App;
