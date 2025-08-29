import { useState, useEffect, useCallback } from '@wordpress/element';
import { TextControl, Button } from '@wordpress/components';
import { Icon, search } from '@wordpress/icons';

// Helper function for making AJAX calls
const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

// Simple debounce function
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
        if (searchQuery.length < 3) {
            setSuggestions([]);
            return;
        }
        try {
            // Use the callAjax helper for consistency
            const response = await callAjax('get_youtube_suggestions', { query: searchQuery });
            if (response.success) {
                setSuggestions(response.data);
                setShowSuggestions(true);
            }
        } catch (error) {
            console.error('Suggestion fetch error:', error);
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
        <form className="atm-autocomplete-container" onSubmit={handleSearchSubmit}>
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
            {showSuggestions && suggestions.length > 0 && (
                <ul className="atm-suggestions-list">
                    {suggestions.map((suggestion, index) => (
                        <li key={index} onMouseDown={() => handleSuggestionClick(suggestion)}>
                            {suggestion}
                        </li>
                    ))}
                </ul>
            )}
        </form>
    );
}

export default AutocompleteSearch;