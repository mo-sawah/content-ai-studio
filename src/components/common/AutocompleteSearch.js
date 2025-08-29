import { useState } from '@wordpress/element';
import { TextControl, Button } from '@wordpress/components';
import { Icon, search } from '@wordpress/icons'; // We no longer need 'filter' from here

function AutocompleteSearch({ onSearch, onFilterClick, disabled }) {
    const [query, setQuery] = useState('');

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        onSearch(query);
    };

    return (
        <div className="atm-autocomplete-container">
            <form className="atm-autocomplete-form" onSubmit={handleSearchSubmit}>
                <TextControl
                    value={query}
                    onChange={(value) => setQuery(value)}
                    placeholder="Search for videos on YouTube..."
                    disabled={disabled}
                />
                <Button type="submit" isPrimary disabled={disabled || !query} className="atm-search-button">
                    <Icon icon={search} />
                </Button>
                <Button
                    isSecondary
                    onClick={onFilterClick}
                    disabled={disabled}
                    className="atm-filter-button"
                >
                    {/* --- THIS IS THE CHANGE: Use an <img> tag for your custom SVG --- */}
                    <img 
                        src={`${atm_studio_data.plugin_url}includes/images/filter.svg`} 
                        alt="Filter" 
                        className="atm-button-icon" 
                    />
                </Button>
            </form>
        </div>
    );
}

export default AutocompleteSearch;