import { Modal, SelectControl } from '@wordpress/components';

function FilterModal({ isOpen, onClose, filters, onFilterChange }) {
    if (!isOpen) {
        return null;
    }

    return (
        <Modal
            title="Search Filters"
            onRequestClose={onClose}
            className="atm-filter-modal"
        >
            <div className="atm-form-container">
                <SelectControl
                    label="Sort By"
                    value={filters.order}
                    options={[
                        { label: 'Relevance', value: 'relevance' },
                        { label: 'Upload Date', value: 'date' },
                        { label: 'View Count', value: 'viewCount' },
                        { label: 'Rating', value: 'rating' },
                    ]}
                    onChange={(value) => onFilterChange('order', value)}
                    // Disable if a date filter is active, as it forces sorting by date
                    disabled={!!filters.publishedAfter}
                />
                <SelectControl
                    label="Duration"
                    value={filters.videoDuration}
                    options={[
                        { label: 'Any', value: 'any' },
                        { label: 'Short (< 4 min)', value: 'short' },
                        { label: 'Medium (4-20 min)', value: 'medium' },
                        { label: 'Long (> 20 min)', value: 'long' },
                    ]}
                    onChange={(value) => onFilterChange('videoDuration', value)}
                />
                <SelectControl
                    label="Upload Date"
                    value={filters.publishedAfter}
                    options={[
                        { label: 'Anytime', value: '' },
                        { label: 'Last Hour', value: 'hour' },
                        { label: 'Today', value: 'day' },
                        { label: 'This Week', value: 'week' },
                        { label: 'This Month', value: 'month' },
                        { label: 'This Year', value: 'year' },
                    ]}
                    onChange={(value) => onFilterChange('publishedAfter', value)}
                />
                {/* The "Apply" button has been removed for a better UX */}
            </div>
        </Modal>
    );
}

export default FilterModal;