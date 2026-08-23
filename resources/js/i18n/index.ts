import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import commonEn from './locales/en/common.json';
import commonId from './locales/id/common.json';
import commonJa from './locales/ja/common.json';
import commonKo from './locales/ko/common.json';
import commonZh from './locales/zh/common.json';
import authEn from './locales/en/auth.json';
import authId from './locales/id/auth.json';
import authJa from './locales/ja/auth.json';
import authKo from './locales/ko/auth.json';
import authZh from './locales/zh/auth.json';
import dashboardEn from './locales/en/dashboard.json';
import dashboardId from './locales/id/dashboard.json';
import dashboardJa from './locales/ja/dashboard.json';
import dashboardKo from './locales/ko/dashboard.json';
import dashboardZh from './locales/zh/dashboard.json';
import manageUsersEn from './locales/en/manage-users.json';
import manageUsersId from './locales/id/manage-users.json';
import manageUsersJa from './locales/ja/manage-users.json';
import manageUsersKo from './locales/ko/manage-users.json';
import manageUsersZh from './locales/zh/manage-users.json';
import companiesEn from './locales/en/companies.json';
import companiesId from './locales/id/companies.json';
import companiesJa from './locales/ja/companies.json';
import companiesKo from './locales/ko/companies.json';
import companiesZh from './locales/zh/companies.json';
import rolesEn from './locales/en/roles.json';
import rolesId from './locales/id/roles.json';
import rolesJa from './locales/ja/roles.json';
import rolesKo from './locales/ko/roles.json';
import rolesZh from './locales/zh/roles.json';
import masterEn from './locales/en/master.json';
import masterId from './locales/id/master.json';
import masterJa from './locales/ja/master.json';
import masterKo from './locales/ko/master.json';
import masterZh from './locales/zh/master.json';
import inventoryEn from './locales/en/inventory.json';
import inventoryId from './locales/id/inventory.json';
import inventoryJa from './locales/ja/inventory.json';
import inventoryKo from './locales/ko/inventory.json';
import inventoryZh from './locales/zh/inventory.json';
import manufacturingEn from './locales/en/manufacturing.json';
import manufacturingId from './locales/id/manufacturing.json';
import manufacturingJa from './locales/ja/manufacturing.json';
import manufacturingKo from './locales/ko/manufacturing.json';
import manufacturingZh from './locales/zh/manufacturing.json';
import qualityEn from './locales/en/quality.json';
import qualityId from './locales/id/quality.json';
import qualityJa from './locales/ja/quality.json';
import qualityKo from './locales/ko/quality.json';
import qualityZh from './locales/zh/quality.json';
import purchasingEn from './locales/en/purchasing.json';
import purchasingId from './locales/id/purchasing.json';
import purchasingJa from './locales/ja/purchasing.json';
import purchasingKo from './locales/ko/purchasing.json';
import purchasingZh from './locales/zh/purchasing.json';
import salesEn from './locales/en/sales.json';
import salesId from './locales/id/sales.json';
import salesJa from './locales/ja/sales.json';
import salesKo from './locales/ko/sales.json';
import salesZh from './locales/zh/sales.json';
import cashBankEn from './locales/en/cash-bank.json';
import cashBankId from './locales/id/cash-bank.json';
import cashBankJa from './locales/ja/cash-bank.json';
import cashBankKo from './locales/ko/cash-bank.json';
import cashBankZh from './locales/zh/cash-bank.json';
import arEn from './locales/en/ar.json';
import arId from './locales/id/ar.json';
import arJa from './locales/ja/ar.json';
import arKo from './locales/ko/ar.json';
import arZh from './locales/zh/ar.json';
import apEn from './locales/en/ap.json';
import apId from './locales/id/ap.json';
import apJa from './locales/ja/ap.json';
import apKo from './locales/ko/ap.json';
import apZh from './locales/zh/ap.json';
import fixedAssetsEn from './locales/en/fixed-assets.json';
import fixedAssetsId from './locales/id/fixed-assets.json';
import fixedAssetsJa from './locales/ja/fixed-assets.json';
import fixedAssetsKo from './locales/ko/fixed-assets.json';
import fixedAssetsZh from './locales/zh/fixed-assets.json';
import reportsEn from './locales/en/reports.json';
import reportsId from './locales/id/reports.json';
import reportsJa from './locales/ja/reports.json';
import reportsKo from './locales/ko/reports.json';
import reportsZh from './locales/zh/reports.json';
import aiEn from './locales/en/ai.json';
import aiId from './locales/id/ai.json';
import aiJa from './locales/ja/ai.json';
import aiKo from './locales/ko/ai.json';
import aiZh from './locales/zh/ai.json';
import aiDocumentsEn from './locales/en/ai-documents.json';
import aiDocumentsId from './locales/id/ai-documents.json';
import aiDocumentsJa from './locales/ja/ai-documents.json';
import aiDocumentsKo from './locales/ko/ai-documents.json';
import aiDocumentsZh from './locales/zh/ai-documents.json';
import auditEn from './locales/en/audit.json';
import auditId from './locales/id/audit.json';
import auditJa from './locales/ja/audit.json';
import auditKo from './locales/ko/audit.json';
import auditZh from './locales/zh/audit.json';
import currenciesEn from './locales/en/currencies.json';
import currenciesId from './locales/id/currencies.json';
import currenciesJa from './locales/ja/currencies.json';
import currenciesKo from './locales/ko/currencies.json';
import currenciesZh from './locales/zh/currencies.json';
import maintenanceEn from './locales/en/maintenance.json';
import maintenanceId from './locales/id/maintenance.json';
import maintenanceJa from './locales/ja/maintenance.json';
import maintenanceKo from './locales/ko/maintenance.json';
import maintenanceZh from './locales/zh/maintenance.json';

export const SUPPORTED_LOCALES = ['id', 'en', 'zh', 'ja', 'ko'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

const resources = {
    id: { common: commonId, auth: authId, dashboard: dashboardId, 'manage-users': manageUsersId, companies: companiesId, roles: rolesId, master: masterId, inventory: inventoryId, manufacturing: manufacturingId, quality: qualityId, purchasing: purchasingId, sales: salesId, 'cash-bank': cashBankId, ar: arId, ap: apId, 'fixed-assets': fixedAssetsId, reports: reportsId, ai: aiId, 'ai-documents': aiDocumentsId, audit: auditId, currencies: currenciesId, maintenance: maintenanceId },
    en: { common: commonEn, auth: authEn, dashboard: dashboardEn, 'manage-users': manageUsersEn, companies: companiesEn, roles: rolesEn, master: masterEn, inventory: inventoryEn, manufacturing: manufacturingEn, quality: qualityEn, purchasing: purchasingEn, sales: salesEn, 'cash-bank': cashBankEn, ar: arEn, ap: apEn, 'fixed-assets': fixedAssetsEn, reports: reportsEn, ai: aiEn, 'ai-documents': aiDocumentsEn, audit: auditEn, currencies: currenciesEn, maintenance: maintenanceEn },
    zh: { common: commonZh, auth: authZh, dashboard: dashboardZh, 'manage-users': manageUsersZh, companies: companiesZh, roles: rolesZh, master: masterZh, inventory: inventoryZh, manufacturing: manufacturingZh, quality: qualityZh, purchasing: purchasingZh, sales: salesZh, 'cash-bank': cashBankZh, ar: arZh, ap: apZh, 'fixed-assets': fixedAssetsZh, reports: reportsZh, ai: aiZh, 'ai-documents': aiDocumentsZh, audit: auditZh, currencies: currenciesZh, maintenance: maintenanceZh },
    ja: { common: commonJa, auth: authJa, dashboard: dashboardJa, 'manage-users': manageUsersJa, companies: companiesJa, roles: rolesJa, master: masterJa, inventory: inventoryJa, manufacturing: manufacturingJa, quality: qualityJa, purchasing: purchasingJa, sales: salesJa, 'cash-bank': cashBankJa, ar: arJa, ap: apJa, 'fixed-assets': fixedAssetsJa, reports: reportsJa, ai: aiJa, 'ai-documents': aiDocumentsJa, audit: auditJa, currencies: currenciesJa, maintenance: maintenanceJa },
    ko: { common: commonKo, auth: authKo, dashboard: dashboardKo, 'manage-users': manageUsersKo, companies: companiesKo, roles: rolesKo, master: masterKo, inventory: inventoryKo, manufacturing: manufacturingKo, quality: qualityKo, purchasing: purchasingKo, sales: salesKo, 'cash-bank': cashBankKo, ar: arKo, ap: apKo, 'fixed-assets': fixedAssetsKo, reports: reportsKo, ai: aiKo, audit: auditKo, currencies: currenciesKo, 'ai-documents': aiDocumentsKo, maintenance: maintenanceKo },
};

i18n.use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources,
        fallbackLng: 'id',
        supportedLngs: SUPPORTED_LOCALES,
        ns: ['common', 'auth', 'dashboard', 'manage-users', 'companies', 'roles', 'master', 'inventory', 'manufacturing', 'quality', 'purchasing', 'sales', 'cash-bank', 'ar', 'ap', 'fixed-assets', 'reports', 'ai', 'ai-documents', 'audit', 'currencies', 'maintenance'],
        defaultNS: 'common',
        interpolation: { escapeValue: false },
        detection: {
            order: ['cookie', 'htmlTag', 'navigator'],
            lookupCookie: 'locale',
            caches: [],
        },
    });

export default i18n;
