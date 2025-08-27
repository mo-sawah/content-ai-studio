// src/components/ArticleGenerator.js
import { useState } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';

import CreativeForm from './CreativeForm';
import NewsForm from './NewsForm';
import RssForm from './RssForm';

function ArticleGenerator({ setActiveView }) {
    const [articleType, setArticleType] = useState('creative');

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
                <SelectControl
                    label="Article Type"
                    value={articleType}
                    options={[
                        { label: 'Creative Article', value: 'creative' },
                        { label: 'Latest News Article', value: 'news' },
                        { label: 'Article from RSS Feed', value: 'rss_feed' },
                    ]}
                    onChange={(value) => setArticleType(value)}
                />

                <hr className="atm-form-divider" />

                {renderForm()}
            </div>
        </div>
    );
}

export default ArticleGenerator;