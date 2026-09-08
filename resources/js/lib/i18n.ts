import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import authEn from '@/lang/en/auth.json';
import commonEn from '@/lang/en/common.json';
import dashboardEn from '@/lang/en/dashboard.json';
import navEn from '@/lang/en/nav.json';
import settingsEn from '@/lang/en/settings.json';
import authEs from '@/lang/es/auth.json';
import commonEs from '@/lang/es/common.json';
import dashboardEs from '@/lang/es/dashboard.json';
import navEs from '@/lang/es/nav.json';
import settingsEs from '@/lang/es/settings.json';

export const SUPPORTED_LOCALES = ['es', 'en'] as const;

export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

export const LOCALE_STORAGE_KEY = 'sendsaas.locale';

const namespaces = [
    'common',
    'nav',
    'auth',
    'settings',
    'dashboard',
] as const;

i18n.use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            es: {
                common: commonEs,
                nav: navEs,
                auth: authEs,
                settings: settingsEs,
                dashboard: dashboardEs,
            },
            en: {
                common: commonEn,
                nav: navEn,
                auth: authEn,
                settings: settingsEn,
                dashboard: dashboardEn,
            },
        },
        fallbackLng: DEFAULT_LOCALE,
        defaultNS: 'common',
        ns: [...namespaces],
        interpolation: { escapeValue: false },
        detection: {
            order: ['localStorage', 'navigator', 'htmlTag'],
            lookupLocalStorage: LOCALE_STORAGE_KEY,
            caches: ['localStorage'],
        },
        react: { useSuspense: false },
    });

function syncHtmlLang(locale: string): void {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('lang', locale);
    }
}

syncHtmlLang(i18n.language || DEFAULT_LOCALE);
i18n.on('languageChanged', syncHtmlLang);

export default i18n;
