import TopicList from "../components/TopicList";

export default function HomePage() {
  return (
    <div className="home-page">
      <h1 className="home-page-title">
        Welcome to Drupal-React Headless Project
      </h1>
      <TopicList />
    </div>
  );
}
