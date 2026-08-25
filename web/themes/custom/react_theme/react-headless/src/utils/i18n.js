import i18n from "i18next";
import { initReactI18next } from "react-i18next";

import enLang from "./i18n/locales/en/en.json";
import deLang from "./i18n/locales/de/de.json";

const resources = {
  en: {
    translation: enLang,
  },
  de: {
    translation: deLang,
  },
};

i18n.use(initReactI18next).init({
  resources,
  lng: "en", // default language
  fallbackLng: "en",
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
