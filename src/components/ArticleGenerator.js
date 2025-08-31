// src/components/ArticleGenerator.js
import { useState, useRef } from '@wordpress/element';
import { DropdownMenu } from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';

import CreativeForm from './CreativeForm';
import NewsForm from './NewsForm';
import RssForm from './RssForm';

function ArticleGenerator({ setActiveView }) {
    const [articleType, setArticleType] = useState('creative');
    const [articleTypeLabel, setArticleTypeLabel] = useState('Creative Article');

    // Custom dropdown component
    const CustomDropdown = ({ label, text, options, onChange, disabled, helpText }) => {
        const dropdownRef = useRef(null);

        return (
            <div className="atm-dropdown-field" ref={dropdownRef}>
                <label className="atm-dropdown-label">{label}</label>
                <DropdownMenu
                    className="atm-custom-dropdown"
                    icon={chevronDown}
                    text={text}
                    controls={options.map(option => ({
                        title: option.label,
                        onClick: () => {
                            onChange(option);
                        }
                    }))}
                    disabled={disabled}
                    popoverProps={{
                        className: 'atm-popover',
                        style: {
                            '--atm-dropdown-width': dropdownRef.current?.offsetWidth
                                ? dropdownRef.current.offsetWidth + 'px'
                                : 'auto'
                        }
                    }}
                />
                {helpText && <p className="atm-dropdown-help">{helpText}</p>}
            </div>
        );
    };

    const articleTypeOptions = [
        { label: 'Creative Article', value: 'creative' },
        { label: 'Latest News Article', value: 'news' },
        { label: 'Article from RSS Feed', value: 'rss_feed' },
    ];

    const renderForm = () => {
        switch (articleType) {
            case 'news':
                return <NewsForm />;
            case 'rss_feed':
                return <RssForm />;
            case 'creative':
            default:
                return <CreativeForm />;
        }
    };

    return (
        <div className="atm-generator-view">
            <div className="atm-view-header">
                <button className="atm-back-btn" onClick={() => setActiveView('hub')}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>
                </button>
                <h3>Generate Article</h3>
            </div>

            <div className="atm-form-container">
                <CustomDropdown
                    label="Article Type"
                    text={articleTypeLabel}
                    options={articleTypeOptions}
                    onChange={(option) => {
                        setArticleType(option.value);
                        setArticleTypeLabel(option.label);
                    }}
                />

                <hr className="atm-form-divider" />

                {renderForm()}
            </div>
        </div>
    );
}

export default ArticleGenerator;