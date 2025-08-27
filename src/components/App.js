// src/components/App.js

import { useState, useEffect } from '@wordpress/element';
import Hub from './Hub';
import ArticleGenerator from './ArticleGenerator'; 
// import ImageGenerator from './ImageGenerator';

function App() {
    // This state holds the currently active view. We read from sessionStorage
    // on first load to remember the last state, defaulting to 'hub'.
    const [activeView, setActiveView] = useState(
        sessionStorage.getItem('atm-active-view') || 'hub'
    );

    // This effect runs every time `activeView` changes, saving the new
    // state to sessionStorage. This is how we remember the state on refresh.
    useEffect(() => {
        sessionStorage.setItem('atm-active-view', activeView);
    }, [activeView]);

    // This function decides which component to show based on the current state
    const renderContent = () => {
        switch (activeView) {
            case 'articles':
                return <ArticleGenerator setActiveView={setActiveView} />;
            case 'images':
                return <div>Image Generator UI will go here. <button onClick={() => setActiveView('hub')}>← Back</button></div>;
            // Add other cases for podcast, etc.

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