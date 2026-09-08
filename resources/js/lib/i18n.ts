import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import authEn from '@/lang/en/auth.json';
import commonEn from '@/lang/en/common.json';
import comunicacionesEn from '@/lang/en/comunicaciones.json';
import contactosEn from '@/lang/en/contactos.json';
import dashboardEn from '@/lang/en/dashboard.json';
import navEn from '@/lang/en/nav.json';
import planesEn from '@/lang/en/planes.json';
import rolesEn from '@/lang/en/roles.json';
import sedesEn from '@/lang/en/sedes.json';
import settingsEn from '@/lang/en/settings.json';
import tenantsEn from '@/lang/en/tenants.json';
import usuariosEn from '@/lang/en/usuarios.json';
import authEs from '@/lang/es/auth.json';
import commonEs from '@/lang/es/common.json';
import comunicacionesEs from '@/lang/es/comunicaciones.json';
import contactosEs from '@/lang/es/contactos.json';
import dashboardEs from '@/lang/es/dashboard.json';
import navEs from '@/lang/es/nav.json';
import planesEs from '@/lang/es/planes.json';
import rolesEs from '@/lang/es/roles.json';
import sedesEs from '@/lang/es/sedes.json';
import settingsEs from '@/lang/es/settings.json';
import tenantsEs from '@/lang/es/tenants.json';
import usuariosEs from '@/lang/es/usuarios.json';

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
    'roles',
    'usuarios',
    'planes',
    'tenants',
    'sedes',
    'comunicaciones',
    'contactos',
] as const;

const isBrowser = typeof document !== 'undefined';

if (isBrowser) {
    i18n.use(LanguageDetector);
}

i18n.use(initReactI18next).init({
    resources: {
        es: {
            common: commonEs,
            nav: navEs,
            auth: authEs,
            settings: settingsEs,
            dashboard: dashboardEs,
            roles: rolesEs,
            usuarios: usuariosEs,
            planes: planesEs,
            tenants: tenantsEs,
            sedes: sedesEs,
            comunicaciones: comunicacionesEs,
            contactos: contactosEs,
        },
        en: {
            common: commonEn,
            nav: navEn,
            auth: authEn,
            settings: settingsEn,
            dashboard: dashboardEn,
            roles: rolesEn,
            usuarios: usuariosEn,
            planes: planesEn,
            tenants: tenantsEn,
            sedes: sedesEn,
            comunicaciones: comunicacionesEn,
            contactos: contactosEn,
        },
    },
    lng: isBrowser ? undefined : DEFAULT_LOCALE,
    fallbackLng: DEFAULT_LOCALE,
    defaultNS: 'common',
    ns: [...namespaces],
    interpolation: { escapeValue: false },
    detection: isBrowser
        ? {
              order: ['localStorage', 'navigator', 'htmlTag'],
              lookupLocalStorage: LOCALE_STORAGE_KEY,
              caches: ['localStorage'],
          }
        : undefined,
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
