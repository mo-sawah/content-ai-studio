// src/index.js
import { render } from "@wordpress/element";
import App from "./components/App";

// Initialize the AI Studio when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("atm-studio-root");
  if (container) {
    render(<App />, container);
  }
});
