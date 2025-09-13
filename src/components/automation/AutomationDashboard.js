// Update the renderCreateCampaign function to use the new components
const renderCreateCampaign = () => {
  switch (selectedCampaignType) {
    case "articles":
      return (
        <AutoArticleGenerator
          setActiveView={setActiveView}
          editingCampaign={editingCampaign}
        />
      );
    case "news":
      return (
        <AutoNewsGenerator
          setActiveView={setActiveView}
          editingCampaign={editingCampaign}
        />
      );
    case "videos":
      return (
        <AutoVideoGenerator
          setActiveView={setActiveView}
          editingCampaign={editingCampaign}
        />
      );
    case "podcasts":
      return (
        <AutoPodcastGenerator
          setActiveView={setActiveView}
          editingCampaign={editingCampaign}
        />
      );
    default:
      return null;
  }
};
