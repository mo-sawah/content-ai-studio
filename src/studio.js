import { render } from '@wordpress/element';
import App from './components/App';
import './studio.scss';

// Find the root element and render our App component
document.addEventListener('DOMContentLoaded', () => {
    const rootEl = document.getElementById('atm-studio-root');
    if (rootEl) {
        render(<App />, rootEl);
    }
});