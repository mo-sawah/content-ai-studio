import { useState, useEffect, useCallback } from '@wordpress/element';
import { TextControl, Button } from '@wordpress/components';
import { Icon, search } from '@wordpress/icons';

// Helper function for making AJAX calls (this was the missing piece)
const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

const debounce = (func, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func(...args), delay);
    };
};

function AutocompleteSearch({ onSearch, disabled }) {
    const [query, setQuery] = useState('');
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);

    const fetchSuggestions = async (searchQuery) => {
        if (searchQuery.length < 2) { // Changed to 2 for quicker feedback
            setSuggestions([]);
            return;
        }
        try {
            const response = await callAjax('get_youtube_suggestions', { query: searchQuery });
            if (response.success) {
                setSuggestions(response.data);
                setShowSuggestions(true);
            } else {
                // If the AJAX call itself fails, hide suggestions
                setShowSuggestions(false);
            }
        } catch (error) {
            console.error('Suggestion fetch error:', error);
            setShowSuggestions(false); // Hide on error
        }
    };

    const debouncedFetch = useCallback(debounce(fetchSuggestions, 300), []);

    useEffect(() => {
        debouncedFetch(query);
    }, [query, debouncedFetch]);

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        setShowSuggestions(false);
        onSearch(query);
    };

    const handleSuggestionClick = (suggestion) => {
        setQuery(suggestion);
        setShowSuggestions(false);
        onSearch(suggestion);
    };

    return (
        <div className="atm-autocomplete-container" style={{ position: 'relative' }}>
            <form className="atm-autocomplete-form" onSubmit={handleSearchSubmit}>
                <TextControl
                    value={query}
                    onChange={(value) => setQuery(value)}
                    onFocus={() => query.length > 2 && setShowSuggestions(true)}
                    onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                    placeholder="Search for videos on YouTube..."
                    disabled={disabled}
                />
                <Button type="submit" isPrimary disabled={disabled || !query} className="atm-search-button">
                    <Icon icon={search} />
                </Button>
            </form>
            {showSuggestions && suggestions.length > 0 && (
                <ul className="atm-suggestions-list">
                    {suggestions.map((suggestion, index) => (
                        <li key={index} onMouseDown={() => handleSuggestionClick(suggestion)}>
                            {suggestion}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default AutocompleteSearch;