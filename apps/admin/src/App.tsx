import './index.sass';
import { Refine } from '@refinedev/core';
import type { ResourceProps } from '@refinedev/core';
import {
  useNotificationProvider,
  ErrorComponent,
} from '@refinedev/antd';
import '@refinedev/antd/dist/reset.css';
import routerProvider from '@refinedev/react-router-v6/legacy';
import { ConfigProvider, App as AntdApp } from 'antd';
import {
  Title,
  Header,
  Sider,
  Footer,
  Layout,
  OffLayoutArea,
} from 'components/layout';
import { authProvider } from './authProvider';
import AuthPage from 'components/authPage';
import HomePage from 'pages/home';
import {
  BarChartOutlined,
  CreditCardOutlined,
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
import TransactionMessageList from 'pages/transaction/message/list';
import FundList from 'pages/transaction/fund/list';
import FundCreate from 'pages/transaction/fund/create';
import TransitionDemoCreate from 'pages/transaction/collection/create';
import ThirdChannelList from 'pages/thirdChannel/list';
import ThirdChannelSettingList from 'pages/thirdChannel/setting/list';
import ProvidersList from 'pages/providers/list';
import ProvidersCreate from 'pages/providers/create';
import ProviderShow from 'pages/providers/show';
import ProviderWhiteList from 'pages/providers/whiteList';
import ProviderWalletList from 'pages/providers/wallet-history/list';
import SystemBankCardList from 'pages/transaction/deposit/systemBankCard/list';
import SystemBankCardsCreate from 'pages/transaction/deposit/systemBankCard/create';
import SystemBankCardShow from 'pages/transaction/deposit/systemBankCard/show';
import DepositList from 'pages/transaction/deposit/list';
import DepositRewardList from 'pages/transaction/deposit/match-deposit-reward/list';
import DepositRewardCreate from 'pages/transaction/deposit/match-deposit-reward/create';
import ProviderUserWalletHistoryList from 'pages/providers/user-wallet-history/list';
import BankList from 'pages/systemSetting/bank/list';
import Env from 'lib/env';
import TagList from 'pages/tag/list';
import TagCreate from 'pages/tag/create';

import { useTranslation } from 'react-i18next';
import './i18n';

import enUS from 'antd/locale/en_US';
import zhCN from 'antd/locale/zh_CN';
import thTH from 'antd/locale/th_TH';
import { useState } from 'react';
import dayjs from 'dayjs';

import 'dayjs/locale/th';
import 'dayjs/locale/en';
import 'dayjs/locale/zh-cn';

initDayjs();

function App() {
  const { t, i18n } = useTranslation();
  const isPaufen = process.env.REACT_APP_IS_PAUFEN;
  const region = Env.getVariable('REGION');
  const temps: (ResourceProps | null)[] = [
    {
      name: 'home',
      list: HomePage,
      icon: <HomeOutlined />,
      options: {
        label: t('navigation.home'),
      },
    },
    {
      name: 'tags',
      list: TagList,
      icon: <TagOutlined />,
      options: {
        label: t('navigation.tags'),
      },
      create: TagCreate,
    },
    isPaufen
      ? {
          name: 'providers',
          list: ProvidersList,
          create: ProvidersCreate,
          show: ProviderShow,
          icon: <QrcodeOutlined />,
          options: {
            label: t('navigation.providerManagement'),
          },
        }
      : null,
    isPaufen
      ? {
          name: 'white-list',
          list: ProviderWhiteList,
          parentName: 'providers',
          options: {
            label: t('navigation.whiteList'),
            hide: true,
          },
        }
      : null,
    isPaufen
      ? {
          name: 'wallet-histories',
          list: ProviderWalletList,
          options: {
            label: t('navigation.providerBalanceAdjustment'),
            hide: true,
          },
          parentName: 'providers',
        }
      : null,
    isPaufen
      ? {
          name: 'user-wallet-history',
          list: ProviderUserWalletHistoryList,
          options: {
            label: t('navigation.providerWalletHistory'),
            hide: true,
          },
          parentName: 'providers',
        }
      : null,
    {
      name: 'merchants',
      list: MerchantList,
      icon: <ShopOutlined />,
      options: {
        label: t('navigation.merchantManagement'),
      },
      create: MerchantCreate,
      show: MerchantShow,
    },
    {
      name: 'white-list',
      list: MerchantWhiteList,
      options: {
        label: t('navigation.whiteList'),
        hide: true,
      },
      parentName: 'merchants',
    },
    {
      name: 'api-white-list',
      list: MerchantApiWhiteList,
      options: {
        label: t('navigation.apiWhiteList'),
        hide: true,
      },
      parentName: 'merchants',
    },
    {
      name: 'banned-list',
      list: MerchantBannedList,
      options: {
        label: t('navigation.bannedList'),
        hide: true,
      },
      parentName: 'merchants',
    },
    {
      name: 'user-wallet-history',
      list: UserWalletHistoryList,
      options: {
        label: t('navigation.merchantWalletHistory'),
        hide: true,
      },
      parentName: 'merchants',
    },
    {
      name: 'wallet-histories',
      list: MerchantWalletList,
      options: {
        label: t('navigation.merchantBalanceAdjustment'),
        hide: true,
      },
      parentName: 'merchants',
    },
    {
      name: 'user-channel-accounts',
      list: UserChannelAccountList,
      icon: <WalletOutlined />,
      options: {
        label: t('navigation.paymentAccountManagement'),
      },
      show: UserChannelShow,
      create: UserChannelCreate,
    },
    {
      name: 'transaction',
      icon: <SwapOutlined />,
      options: {
        label: t('navigation.transactionManagement'),
      },
    },
    {
      name: 'transactions',
      options: {
        label: t('navigation.collection'),
      },
      list: CollectionList,
      show: CollectionShow,
      create: TransitionDemoCreate,
      parentName: 'transaction',
    },
    // isPaufen
    //     ? {
    //           name: "transaction-rewards",
    //           list: TransactionRewardList,
    //           create: TransactionRewardCreate,
    //           options: {
    //               label: "交易奖励",
    //               hide: true,
    //           },
    //           parentName: "transactions",
    //       }
    //     : null,
    {
      name: 'withdraws',
      options: {
        label: t('navigation.payment'),
      },
      list: PayForAnotherList,
      show: PayForAnotherShow,
      parentName: 'transaction',
    },
    isPaufen
      ? {
          name: 'deposit',
          list: DepositList,
          options: {
            label: t('navigation.providerDeposit'),
          },
          parentName: 'transaction',
        }
      : null,
    isPaufen
      ? {
          name: 'system-bank-cards',
          list: SystemBankCardList,
          create: SystemBankCardsCreate,
          icon: <CreditCardOutlined />,
          show: SystemBankCardShow,
          options: {
            label: t('navigation.systemBankCard'),
            hide: true,
          },
          parentName: 'deposit',
        }
      : null,
    isPaufen
      ? {
          name: 'matching-deposit-rewards',
          list: DepositRewardList,
          create: DepositRewardCreate,
          options: {
            label: t('navigation.quickDepositReward'),
            hide: true,
          },
          parentName: 'deposit',
        }
      : null,
    region !== 'CN'
      ? {
          name: 'notifications',
          options: {
            label: t('navigation.sms'),
          },
          list: TransactionMessageList,
          parentName: 'transaction',
        }
      : null,
    region !== 'CN'
      ? {
          name: 'internal-transfers',
          options: {
            label: t('navigation.fundManagement'),
          },
          list: FundList,
          create: FundCreate,
          parentName: 'transaction',
        }
      : null,
    {
      name: 'child-withdraws',
      options: {
        label: t('navigation.childWithdrawSplit'),
        hide: true,
      },
      show: ChildWithdrawCreate,
    },
    {
      name: 'user-bank-cards',
      options: {
        label: t('navigation.merchantBankCardList'),
        hide: true,
      },
      list: UserBankCardList,
    },
    {
      name: 'online-ready-for-matching-users',
      list: LiveList,
      icon: <FieldTimeOutlined />,
      options: {
        label: t('navigation.liveStatus'),
      },
    },
    {
      name: 'statistics/v1',
      list: FinanceStatisticPage,
      icon: <BarChartOutlined />,
      options: {
        label: t('navigation.financeReport'),
        route: 'finance-statistics',
      },
    },
    isPaufen
      ? null
      : {
          name: 'providers',
          list: ProviderList,
          create: ProviderCreate,
          icon: <QrcodeOutlined />,
          options: {
            label: t('navigation.groupManagement'),
          },
        },
    {
      name: 'merchant-transaction-groups',
      list: TransactionGroupList,
      options: {
        label: t('navigation.collectionLine'),
        hide: true,
      },
      parentName: 'providers',
    },
    {
      name: 'merchant-matching-deposit-groups',
      list: DepositGroupList,
      options: {
        label: t('navigation.collectionLine'),
        hide: true,
      },
      parentName: 'providers',
    },
    {
      name: 'channels',
      list: ChannelList,
      options: {
        label: t('navigation.channelManagement'),
      },
      icon: <DeploymentUnitOutlined />,
    },
    {
      name: 'thirdchannel',
      list: ThirdChannelList,
      options: {
        label: t('navigation.thirdPartyManagement'),
      },
    },
    {
      name: 'merchant-third-channel',
      list: ThirdChannelSettingList,
      options: {
        hide: true,
      },
      parentName: 'thirdchannel',
    },
    {
      name: 'feature-toggles',
      list: SystemSettingList,
      options: {
        label: t('navigation.systemSettings'),
      },
      icon: <SettingOutlined />,
    },
    {
      name: 'banks',
      list: BankList,
      options: {
        label: t('navigation.supportedBanks'),
        hide: true,
      },
      parentName: 'feature-toggles',
    },
    {
      name: 'sub-accounts',
      icon: <LockOutlined />,
      options: {
        label: t('navigation.permissionManagement'),
      },
      list: PermissionList,
      create: SubAccountCreate,
      show: SubAccountShow,
    },
    {
      name: 'login-white-list',
      options: {
        label: t('navigation.adminLoginWhiteList'),
        hide: true,
      },
      list: LoginWhiteList,
      parentName: 'sub-accounts',
    },
    // {
    //     name: "posts",
    //     list: PostList,
    //     create: PostCreate,
    //     edit: PostEdit,
    //     show: PostShow,
    // },
  ];

  const [currentLocale, setCurrentLocale] = useState(i18n.language);

  const resources: ResourceProps[] = temps.filter(
    t => t !== null
  ) as ResourceProps[];

  const i18nProvider = {
    translate: (key: string, options?: any) => t(key, options),
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
        return zhCN;
      case 'en':
        return enUS;
      case 'th':
        return thTH;
      default:
        return zhCN;
    }
  };

  return (
    <>
      <Helmet>
        <link
          rel="icon"
          href={process.env.REACT_APP_FAVICON_SRC}
          sizes="16x16"
        />
      </Helmet>
      <ConfigProvider
        form={{
          validateMessages: {
            required: t('validation.required'),
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
            legacyRouterProvider={routerProvider}
            legacyAuthProvider={authProvider}
            Title={Title}
            Header={Header}
            Sider={Sider}
            Footer={Footer}
            Layout={Layout}
            OffLayoutArea={OffLayoutArea}
            LoginPage={AuthPage}
            catchAll={<ErrorComponent />}
            i18nProvider={{
              translate: (key, options) => {
                const result = i18nProvider.translate(key, options);
                // Ensure translate always returns a string (fix typing issue)
                if (typeof result === 'string') return result;
                if (
                  typeof result === 'object' &&
                  result !== null &&
                  'toString' in result
                ) {
                  // fall back to object's toString, or JSON.stringify if needed
                  try {
                    return result.toString();
                  } catch {
                    return JSON.stringify(result);
                  }
                }
                return String(result);
              },
              changeLocale: i18nProvider.changeLocale,
              getLocale: i18nProvider.getLocale,
            }}
            options={{
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
            resources={resources}
          />
        </AntdApp>
      </ConfigProvider>
    </>
  );
}

export default App;
