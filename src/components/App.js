import { useState, useEffect } from '@wordpress/element';
import Hub from './Hub';
// We will import and build these generator components in our next steps
// import ArticleGenerator from './ArticleGenerator';

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
                // For now, this is a placeholder. We will build the real component next.
                return (
                    <div>
                        <h4><button className="atm-back-btn" onClick={() => setActiveView('hub')}>←</button> Generate Article</h4>
                        <p>The Article Generator UI will be built here.</p>
                    </div>
                );
            // Add cases for 'images', 'podcast', etc. later

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