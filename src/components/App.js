// src/components/App.js

import { useState, useEffect } from '@wordpress/element';
import Hub from './Hub';
import ArticleGenerator from './ArticleGenerator'; 
import ImageGenerator from './ImageGenerator'; // Import the new component
import PodcastGenerator from './PodcastGenerator'; 
import SpeechToText from './SpeechToText';

function App() {
    const [activeView, setActiveView] = useState(
        sessionStorage.getItem('atm-active-view') || 'hub'
    );

    useEffect(() => {
        sessionStorage.setItem('atm-active-view', activeView);
    }, [activeView]);

    const renderContent = () => {
        switch (activeView) {
            case 'articles':
                return <ArticleGenerator setActiveView={setActiveView} />;

            case 'images': // Add this case
                return <ImageGenerator setActiveView={setActiveView} />;

            case 'podcast':
                return <PodcastGenerator setActiveView={setActiveView} />;
            
            case 'speech_to_text':
                return <SpeechToText setActiveView={setActiveView} />;
            
                // Add other cases for podcast, etc. later

            default: // The 'hub' view
                return <Hub setActiveView={setActiveView} />;
        }
    };

    return (
        <div className="atm-studio-container">
            {renderContent()}
        </div>
    );
}

export default App;