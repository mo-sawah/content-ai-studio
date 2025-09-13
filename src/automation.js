// src/automation.js
import { render } from "@wordpress/element";
import AutomationApp from "./components/automation/AutomationApp";

// Import your SCSS file here to apply styles to the entire app
import "./styles/automation-dashboard.scss";

// Render the automation app
const automationRoot = document.getElementById("atm-automation-root");
if (automationRoot) {
  render(<AutomationApp />, automationRoot);
}
