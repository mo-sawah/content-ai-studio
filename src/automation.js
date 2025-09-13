// src/automation.js
import { render } from "@wordpress/element";
import AutomationApp from "./components/automation/AutomationApp";

// Import the new dashboard styles
import "./automation.scss";

// Render the automation app
const automationRoot = document.getElementById("atm-automation-root");
if (automationRoot) {
  render(<AutomationApp />, automationRoot);
}
