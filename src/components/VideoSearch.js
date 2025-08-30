import { useState, useEffect } from '@wordpress/element';
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
                setStatusMessage(response.data.length > 0 ? `Showing results for "${query}"` : 'No results found.');
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            setStatusMessage(`Error: ${error.message}`);
        } finally {
            setIsLoading(false);
        }
    };
    
    const handleFilterChange = (key, value) => {
        setFilters(prevFilters => {
            const newFilters = { ...prevFilters, [key]: value };
            if (key === 'publishedAfter' && value !== '') {
                newFilters.order = 'date';
            }
            // If user manually changes sort order back to relevance, clear the date filter
            if (key === 'order' && value === 'relevance') {
                newFilters.publishedAfter = '';
            }
            return newFilters;
        });
    };

    // --- NEW: Automatically re-search when filters change ---
    useEffect(() => {
        // Don't run on the initial render or if no search has been made yet
        if (lastQuery) {
            handleSearch(lastQuery);
        }
    }, [filters]); // This effect runs whenever the 'filters' object changes

    const handleEmbed = (url) => {
        const isBlockEditor = !!wp.data.select('core/block-editor');
        if (isBlockEditor) {
            const embedBlock = createBlock('core/embed', {
                url,
                providerNameSlug: 'youtube',
                align: 'wide', // Set a responsive width
                className: 'atm-youtube-embed',
            });
            insertBlocks(embedBlock);
        } else {
            const embedCode = `\n<div class="wp-video" style="width: 100%;"><iframe src="${url.replace('/watch?v=', '/embed/')}" width="500" height="281" style="width: 100%; aspect-ratio: 16/9; height: auto;" frameborder="0" allowfullscreen></iframe></div>\n`;
            window.send_to_editor(embedCode);
        }
        setStatusMessage('✅ Video embedded successfully!');
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
                    Search for videos on YouTube. Use filters for more specific results.
                </p>

                {isLoading && <div className="atm-video-spinner-container"><Spinner /></div>}
                
                {!isLoading && searchResults.length > 0 && (
                    <div className="atm-video-results-list">
                        {searchResults.map(video => (
                            <VideoResult key={video.id} video={video} onEmbed={handleEmbed} />
                        ))}
                    </div>
                )}

                 {!isLoading && searchResults.length === 0 && <p className="atm-status-message">{statusMessage}</p>}
            </div>

            <FilterModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                filters={filters}
                onFilterChange={handleFilterChange}
            />
        </div>
    );
}

export default VideoSearch;