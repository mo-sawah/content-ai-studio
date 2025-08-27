# Content AI Studio

**Your all-in-one AI toolkit to transform articles into compelling images, podcasts, and more.**

Content AI Studio is a WordPress plugin that integrates powerful AI content generation tools directly into your post editor. Whether you're using the Classic Editor or the Gutenberg Block Editor, you can effortlessly create full articles, generate stunning featured images, and even convert your posts into ready-to-listen podcasts.

## Key Features

* **AI Article Generation**:
    * **Creative Mode**: Generate a full article from just a keyword or a title, with options for different writing styles and lengths.
    * **Latest News Mode**: Create timely articles based on the latest news for a given topic by pulling data from sources like NewsAPI.org, GNews.io, and The Guardian.
    * **RSS Feed Mode**: Rewrite and enhance content from any RSS feed you provide, with an optional "Deep Search" to scrape the full article content for the highest quality rewrites.
* **AI Image Generation**:
    * Generate and automatically set the **Featured Image** for your post.
    * Create and insert **Inline Images** directly into your content while you write.
* **AI Podcast Generation**:
    * Automatically generate a conversational **Podcast Script** from your finished article.
    * Generate high-quality **MP3 Audio** from the script using different AI voices.
    * An interactive audio player is **automatically embedded** at the top of posts with a podcast.
* **Flexible API Support**:
    * Connects to multiple AI models through the **OpenRouter API**.
    * Uses the **OpenAI API** for high-quality TTS audio and DALL-E 3 image generation.

## Installation

1.  Download the latest release ZIP file from the [Releases](https://github.com/mo-sawah/content-ai-studio/releases) page.
2.  In your WordPress admin dashboard, go to `Plugins` > `Add New`.
3.  Click `Upload Plugin` and select the ZIP file you downloaded.
4.  Activate the plugin.

## Configuration

1.  After activation, navigate to **AI Studio -> Settings** in your WordPress admin menu.
2.  Enter your API keys for the services you wish to use (OpenRouter is required for article/script generation; OpenAI is required for audio/image generation).
3.  Configure your default AI models and RSS feeds as needed.
4.  Save your settings.

## Development

To contribute to or modify the plugin:

1.  Clone the repository: `git clone https://github.com/mo-sawah/content-ai-studio.git`
2.  Navigate to the plugin directory: `cd content-ai-studio`
3.  Install the required Node.js packages: `npm install`
4.  Run the development script to watch for changes in the `/src` directory and build them automatically: `npm start`
5.  To create a final production build, run: `npm run build`

---

**Note:** This plugin is currently under development.
