import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { Spinner } from '@wordpress/components';
import AutocompleteSearch from './common/AutocompleteSearch';
import VideoResult from './common/VideoResult';
import FilterModal from './common/FilterModal';

const callAjax = (action, data) => jQuery.ajax({ url: atm_studio_data.ajax_url, type: 'POST', data: { action, nonce: atm_studio_data.nonce, ...data } });

function VideoSearch({ setActiveView }) {
    const [searchResults, setSearchResults] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState('Enter a search term to find YouTube videos.');
    const [lastQuery, setLastQuery] = useState('');
    
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [filters, setFilters] = useState({
        order: 'relevance',
        videoDuration: 'any',
        publishedAfter: '',
    });

    const { insertBlocks } = useDispatch('core/block-editor');

    const handleSearch = async (query) => {
        if (!query) return;
        setLastQuery(query);
        setIsLoading(true);
        setSearchResults([]);
        setStatusMessage(`Searching for "${query}"...`);

        try {
            const response = await callAjax('search_youtube', { query, filters });
            if (response.success) {
                setSearchResults(response.data);
                setStatusMessage(response.data.length > 0 ? `Showing ${response.data.length} results for "${query}"` : 'No results found.');
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };
    
    // --- UPDATED: This function now fixes the date filter logic ---
    const handleFilterChange = (key, value) => {
        setFilters(prevFilters => {
            const newFilters = { ...prevFilters, [key]: value };
            // If a date filter is set, automatically switch to sort by date for accurate results.
            if (key === 'publishedAfter' && value !== '') {
                newFilters.order = 'date';
            }
            return newFilters;
        });
    };

    const applyFiltersAndSearch = () => {
        setIsModalOpen(false);
        if (lastQuery) {
            handleSearch(lastQuery);
        }
    };

    // --- UPDATED: This function now creates a wider embed block ---
    const handleEmbed = (url) => {
        const isBlockEditor = !!wp.data.select('core/block-editor');
        if (isBlockEditor) {
            const embedBlock = createBlock('core/embed', {
                url: url,
                providerNameSlug: 'youtube',
                align: 'wide', // This makes the block wider in most themes
                className: 'atm-youtube-embed', // For any custom CSS you might want
            });
            insertBlocks(embedBlock);
        } else {
            // Classic Editor
            const embedCode = `\n${url}\n`;
            window.send_to_editor(embedCode);
        }
        setStatusMessage('✅ Video embedded successfully!');
        setTimeout(() => setStatusMessage(''), 3000);
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')} disabled={isLoading}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>YouTube Video Search</h3>
            </div>
            
            <div className="atm-video-search-container">
                <AutocompleteSearch
                    onSearch={handleSearch}
                    onFilterClick={() => setIsModalOpen(true)}
                    disabled={isLoading}
                />
                <p className="components-base-control__help" style={{ marginTop: '-1rem' }}>
                    Search for videos on YouTube.
                </p>

                {isLoading && <div className="atm-video-spinner-container"><Spinner /></div>}
                
                {!isLoading && searchResults.length === 0 && <p className="atm-status-message">{statusMessage}</p>}
                
                {searchResults.length > 0 && (
                    <div className="atm-video-results-list">
                        {searchResults.map(video => (
                            <VideoResult key={video.id} video={video} onEmbed={handleEmbed} />
                        ))}
                    </div>
                )}
            </div>

            <FilterModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                filters={filters}
                onFilterChange={handleFilterChange}
                onApply={applyFiltersAndSearch}
            />
        </div>
    );
}

export default VideoSearch;