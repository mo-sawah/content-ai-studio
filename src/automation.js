// src/automation.js
import { render } from "@wordpress/element";
import AutomationApp from "./components/automation/AutomationApp";

// Ensure the root element exists
document.addEventListener("DOMContentLoaded", () => {
  const rootEl = document.getElementById("atm-automation-root");
  if (rootEl) {
    render(<AutomationApp />, rootEl);
  }
});
