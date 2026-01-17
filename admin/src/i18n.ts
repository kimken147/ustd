// src/i18n.ts
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import Backend from 'i18next-http-backend'; // ⭐ 加入這行

i18n
  .use(Backend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'zh-CN',
    lng: 'zh-CN', // 預設語言
    interpolation: {
      escapeValue: false,
    },
    supportedLngs: ['en', 'zh-CN', 'th'],
    backend: {
      loadPath: '/locales/{{lng}}/{{ns}}.json', // locale files path
    },
    ns: [
      'common',
      'tags',
      'providers',
      'merchant',
      'userChannel',
      'transaction',
      'live',
      'financeReport',
      'channel',
      'thirdParty',
      'systemSettings',
      'permission',
    ],
    defaultNS: 'common',
  });

export default i18n;
