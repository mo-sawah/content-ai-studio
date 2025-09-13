import { render } from "@wordpress/element";
import AutomationApp from "./components/automation/AutomationApp";
import "./automation.scss";

const automationRoot = document.getElementById("atm-automation-root");
if (automationRoot) {
  render(<AutomationApp />, automationRoot);
}
