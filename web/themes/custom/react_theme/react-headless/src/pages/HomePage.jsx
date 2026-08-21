import TopicList from "../components/TopicList";
import { useTranslation } from "react-i18next";

export default function HomePage() {
  const { t } = useTranslation();

  return (
    <div className="home-page">
      <h1 className="home-page-title">
        {t("app.welcomeHeading")}
      </h1>
      <TopicList />
    </div>
  );
}
