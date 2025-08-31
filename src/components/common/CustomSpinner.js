import { createElement } from '@wordpress/element';

function CustomSpinner() {
    // atm_studio_data is made available to our JS by WordPress
    const spinnerUrl = `${atm_studio_data.plugin_url}assets/images/spin.svg`;

    return (
        <img 
            src={spinnerUrl} 
            className="atm-custom-spinner" 
            alt="Loading..."
            // Add inline style to ensure it's visible while CSS loads
            style={{ width: '20px', height: '20px' }} 
        />
    );
}

export default CustomSpinner;