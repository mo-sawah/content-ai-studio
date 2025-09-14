// src/components/automation/AutoCreativeForm.js
import { useState, useEffect } from "@wordpress/element";
import {
  TextControl,
  TextareaControl,
  SelectControl,
} from "@wordpress/components";

function AutoCreativeForm({
  campaignData,
  setCampaignData,
  isAutomation = true,
}) {
  // Options for dropdowns with user-friendly labels
  const modelOptions = [
    { label: "Use Default Model", value: "" },
    { label: "OpenAI: GPT-4o (Best All-Around)", value: "openai/gpt-4o" },
    {
      label: "Anthropic: Claude 3 Opus (Top-Tier Writing)",
      value: "anthropic/claude-3-5-sonnet-20241022",
    },
    {
      label: "Google: Gemini 1.5 Flash (Fast & Capable)",
      value: "google/gemini-flash-1.5",
    },
    {
      label: "Meta: Llama 3 70B (Great Open Source)",
      value: "meta-llama/llama-3-70b-instruct",
    },
  ];

  const styleOptions = [
    { label: "Standard / SEO-Optimized", value: "default_seo" },
    { label: "Professional Business", value: "professional" },
    { label: "Conversational & Friendly", value: "conversational" },
    { label: "Technical / Expert", value: "technical" },
    { label: "News / Journalistic", value: "news" },
    { label: "Educational / Tutorial", value: "educational" },
  ];

  const wordCountOptions = [
    { label: "Default", value: "" },
    { label: "Short (~500 words)", value: "500" },
    { label: "Medium (~800 words)", value: "800" },
    { label: "Long (~1200 words)", value: "1200" },
    { label: "Very Long (~2000 words)", value: "2000" },
  ];

  const creativityOptions = [
    { label: "Conservative (Factual)", value: "low" },
    { label: "Balanced", value: "medium" },
    { label: "Creative (Dynamic)", value: "high" },
  ];

  // Update campaign data helpers
  const updateSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      settings: {
        ...prev.settings,
        [key]: value,
      },
    }));
  };

  const updateBasicSetting = (key, value) => {
    setCampaignData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  return (
    <div className="atm-form-container">
      {/* Content Configuration Section */}
      <div className="atm-form-section">
        <h3>Content Configuration</h3>

        <TextControl
          label="Keyword"
          placeholder="e.g., AI in digital marketing"
          value={campaignData.keyword || ""}
          onChange={(value) => updateBasicSetting("keyword", value)}
          help="The main topic or keyword for your automated articles"
        />

        <TextControl
          label="Article Title (Optional)"
          placeholder="Leave empty to auto-generate unique titles"
          value={campaignData.article_title || ""}
          onChange={(value) => updateBasicSetting("article_title", value)}
          help="Provide a specific title or let AI generate unique ones with different angles"
        />

        {/* AI Settings Grid */}
        <div className="atm-grid-3">
          <SelectControl
            label="AI Model"
            value={campaignData.settings?.ai_model || ""}
            options={modelOptions}
            onChange={(value) => updateSetting("ai_model", value)}
            help="Choose the AI model for content generation"
          />

          <SelectControl
            label="Writing Style"
            value={campaignData.settings?.writing_style || "default_seo"}
            options={styleOptions}
            onChange={(value) => updateSetting("writing_style", value)}
            help="Select the tone and style for your content"
          />

          <SelectControl
            label="Word Count"
            value={campaignData.settings?.word_count?.toString() || ""}
            options={wordCountOptions}
            onChange={(value) => {
              updateSetting("word_count", value ? parseInt(value) : 0);
            }}
            help="Target article length"
          />
        </div>

        {/* Creativity Level */}
        <SelectControl
          label="Creativity Level"
          value={campaignData.settings?.creativity_level || "high"}
          options={creativityOptions}
          onChange={(value) => updateSetting("creativity_level", value)}
          help="Control how creative vs factual the content should be"
        />

        {/* Custom Prompt */}
        <TextareaControl
          label="Custom Prompt (Optional)"
          placeholder="Leave empty to use the selected Writing Style. If you write a prompt here, it will override the writing style template."
          value={campaignData.settings?.custom_prompt || ""}
          onChange={(value) => updateSetting("custom_prompt", value)}
          rows={6}
          help="For very specific content requirements. This will override the selected writing style."
        />
      </div>
    </div>
  );
}

export default AutoCreativeForm;
