import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import commonEn from './locales/en/common.json';
import commonId from './locales/id/common.json';
import commonJa from './locales/ja/common.json';
import commonKo from './locales/ko/common.json';
import commonZh from './locales/zh/common.json';

export const SUPPORTED_LOCALES = ['id', 'en', 'zh', 'ja', 'ko'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

const resources = {
    id: { common: commonId },
    en: { common: commonEn },
    zh: { common: commonZh },
    ja: { common: commonJa },
    ko: { common: commonKo },
};

i18n.use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources,
        fallbackLng: 'id',
        supportedLngs: SUPPORTED_LOCALES,
        ns: ['common'],
        defaultNS: 'common',
        interpolation: { escapeValue: false },
        detection: {
            order: ['cookie', 'htmlTag', 'navigator'],
            lookupCookie: 'locale',
            caches: [],
        },
    });

export default i18n;
