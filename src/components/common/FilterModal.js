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
                        { label: 'Short (< 4 minutes)', value: 'short' },
                        { label: 'Medium (4-20 minutes)', value: 'medium' },
                        { label: 'Long (> 20 minutes)', value: 'long' },
                    ]}
                    onChange={(value) => onFilterChange('videoDuration', value)}
                />
                <SelectControl
                    label="Upload Date"
                    value={filters.publishedAfter}
                    options={[
                        { label: 'Anytime', value: '' },
                        { label: 'Last Hour', value: new Date(Date.now() - 3600 * 1000).toISOString() },
                        { label: 'Today', value: new Date(Date.now() - 86400 * 1000).toISOString() },
                        { label: 'This Week', value: new Date(Date.now() - 7 * 86400 * 1000).toISOString() },
                        { label: 'This Month', value: new Date(Date.now() - 30 * 86400 * 1000).toISOString() },
                        { label: 'This Year', value: new Date(Date.now() - 365 * 86400 * 1000).toISOString() },
                    ]}
                    onChange={(value) => onFilterChange('publishedAfter', value)}
                />
                <Button isPrimary onClick={onApply}>
                    Apply Filters
                </Button>
            </div>
        </Modal>
    );
}

export default FilterModal;