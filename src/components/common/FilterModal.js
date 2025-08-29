import { Modal, Button, SelectControl } from '@wordpress/components';

function FilterModal({ isOpen, onClose, filters, onFilterChange, onApply }) {
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
                    // --- THE FIX: Using static string values ---
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
                <Button isPrimary onClick={onApply}>
                    Apply Filters & Search
                </Button>
            </div>
        </Modal>
    );
}

export default FilterModal;