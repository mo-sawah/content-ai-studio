import { Button } from '@wordpress/components';

// The `setActiveView` function is passed down from App.js
function Hub({ setActiveView }) {
    return (
        <div className="atm-hub">
            <h2>How can I help you today :)</h2>
            <p className="atm-hub-subtitle">Generate articles, images, podcasts and many more soon</p>
            <div className="atm-hub-actions">
                <Button isPrimary onClick={() => setActiveView('articles')}>Generate Articles</Button>
                <Button isPrimary onClick={() => setActiveView('images')}>Generate Images</Button>
                <Button isPrimary onClick={() => setActiveView('podcast')}>Generate Podcast</Button>
                <Button isPrimary onClick={() => setActiveView('graphs')}>Generate Graphs</Button>
                <Button isPrimary disabled>Generate Videos</Button>
                <Button isSecondary disabled>Coming Soon</Button>
            </div>
            <p className="atm-hub-footer">Your Day to Day AI helper, Content AI Studio.</p>
        </div>
    );
}

export default Hub;