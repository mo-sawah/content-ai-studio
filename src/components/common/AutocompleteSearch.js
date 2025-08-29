import { useState } from '@wordpress/element';
import { TextControl, Button } from '@wordpress/components';
import { Icon, search, filter } from '@wordpress/icons';

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
                <Button 
                    type="submit" 
                    isPrimary 
                    disabled={disabled || !query} 
                    className="atm-search-button"
                >
                    <Icon icon={search} />
                </Button>
                <Button
                    isSecondary
                    onClick={onFilterClick}
                    disabled={disabled}
                    className="atm-filter-button"
                >
                    <Icon icon={filter} />
                </Button>
            </form>
        </div>
    );
}

export default AutocompleteSearch;