// src/automation.js
import { render } from "@wordpress/element";
import AutomationApp from "./components/automation/AutomationApp";

// Wait for DOM to be ready
document.addEventListener("DOMContentLoaded", () => {
  const rootElement = document.getElementById("atm-automation-root");
  if (rootElement) {
    render(<AutomationApp />, rootElement);
  } else {
    console.error("ATM: Automation root element not found");
  }
});

// Also try immediate render in case DOM is already loaded
const rootElement = document.getElementById("atm-automation-root");
if (rootElement) {
  render(<AutomationApp />, rootElement);
}
