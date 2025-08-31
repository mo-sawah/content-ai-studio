// src/components/App.js

import { useState, useEffect } from "@wordpress/element";
import Hub from "./Hub";
import ArticleGenerator from "./ArticleGenerator";
import ImageGenerator from "./ImageGenerator"; // Import the new component
import PodcastGenerator from "./PodcastGenerator";
import SpeechToText from "./SpeechToText";
import Translator from "./Translator"; // <-- Import the new component
import VideoSearch from "./VideoSearch";
import ChartGenerator from "./ChartGenerator";
import KeyTakeawaysGenerator from "./KeyTakeawaysGenerator";

function App() {
  const [activeView, setActiveView] = useState(
    sessionStorage.getItem("atm-active-view") || "hub"
  );

  useEffect(() => {
    sessionStorage.setItem("atm-active-view", activeView);
  }, [activeView]);

  const renderContent = () => {
    switch (activeView) {
      case "articles":
        return <ArticleGenerator setActiveView={setActiveView} />;

      case "images": // Add this case
        return <ImageGenerator setActiveView={setActiveView} />;

      case "podcast":
        return <PodcastGenerator setActiveView={setActiveView} />;

      case "speech_to_text":
        return <SpeechToText setActiveView={setActiveView} />;

      case "translate":
        return <Translator setActiveView={setActiveView} />;

      case "video":
        return <VideoSearch setActiveView={setActiveView} />;

      case "graphs":
        return <ChartGenerator setActiveView={setActiveView} />;

      case "takeaways":
        return <KeyTakeawaysGenerator setActiveView={setActiveView} />;

      default: // The 'hub' view
        return <Hub setActiveView={setActiveView} />;
    }
  };

  return <div className="atm-studio-container">{renderContent()}</div>;
}

export default App;
