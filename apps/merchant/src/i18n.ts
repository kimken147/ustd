import i18n from "i18next";
import { initReactI18next } from "react-i18next"; // https://react.i18next.com/latest/using-with-hooks
import detector from "i18next-browser-languagedetector";
import Backend from "i18next-http-backend"; // adding lazy loading for translations, more information here: https://github.com/i18next/i18next-http-backend

i18n.use(Backend)
    .use(detector)
    .use(initReactI18next)
    .init({
        supportedLngs: ["zh-CN", "en", "th"],
        backend: {
            loadPath: "/locales/{{lng}}/{{ns}}.json", // locale files path
        },
        defaultNS: "common",
        fallbackLng: "zh-CN",
        lng: "zh-CN",
        interpolation: {
            escapeValue: false,
        },
    });

export default i18n;
