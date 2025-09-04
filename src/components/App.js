// src/components/App.js
import { useState } from '@wordpress/element';
import WelcomeHub from './WelcomeHub';
import ArticleGenerator from './ArticleGenerator';
import ImageGenerator from './ImageGenerator';
import PodcastGenerator from './PodcastGenerator';
import KeyTakeawaysGenerator from './KeyTakeawaysGenerator';
import SpeechToText from './SpeechToText';
import Translator from './Translator';
import VideoSearch from './VideoSearch';
import ChartGenerator from './ChartGenerator';

function App() {
    const [activeView, setActiveView] = useState('hub');

    const navigationItems = [
        {
            section: 'CONTENT GENERATION',
            items: [
                { id: 'articles', label: 'Generate Articles', icon: '📝' },
                { id: 'images', label: 'Generate Images', icon: '🖼️' },
                { id: 'podcast', label: 'Generate Podcast', icon: '🎙️' },
                { id: 'takeaways', label: 'Key Takeaways', icon: '📋' }
            ]
        },
        {
            section: 'TOOLS & UTILITIES',
            items: [
                { id: 'speech', label: 'Speech to Text', icon: '🎤' },
                { id: 'translate', label: 'Translate Text', icon: '🌐' },
                { id: 'video', label: 'Video Search', icon: '📹' },
                { id: 'charts', label: 'Generate Charts', icon: '📊' }
            ]
        }
    ];

    const renderActiveView = () => {
        switch (activeView) {
            case 'hub':
                return <WelcomeHub setActiveView={setActiveView} />;
            case 'articles':
                return <ArticleGenerator setActiveView={setActiveView} />;
            case 'images':
                return <ImageGenerator setActiveView={setActiveView} />;
            case 'podcast':
                return <PodcastGenerator setActiveView={setActiveView} />;
            case 'takeaways':
                return <KeyTakeawaysGenerator setActiveView={setActiveView} />;
            case 'speech':
                return <SpeechToText setActiveView={setActiveView} />;
            case 'translate':
                return <Translator setActiveView={setActiveView} />;
            case 'video':
                return <VideoSearch setActiveView={setActiveView} />;
            case 'charts':
                return <ChartGenerator setActiveView={setActiveView} />;
            default:
                return <WelcomeHub setActiveView={setActiveView} />;
        }
    };

    const getPageTitle = () => {
        const titles = {
            hub: 'AI Studio',
            articles: 'Generate Articles',
            images: 'Generate Images',
            podcast: 'Generate Podcast',
            takeaways: 'Key Takeaways',
            speech: 'Speech to Text',
            translate: 'Translate Text',
            video: 'YouTube Video Search',
            charts: 'Generate Charts'
        };
        return titles[activeView] || 'AI Studio';
    };

    const getPageSubtitle = () => {
        const subtitles = {
            hub: 'Content Creation Suite',
            articles: 'Create high-quality content with AI assistance',
            images: 'Generate stunning visuals powered by AI',
            podcast: 'Turn content into engaging audio experiences',
            takeaways: 'Extract key insights from your content',
            speech: 'Convert audio into accurate text',
            translate: 'Translate content across languages',
            video: 'Find and embed YouTube videos',
            charts: 'Create beautiful data visualizations'
        };
        return subtitles[activeView] || 'Content Creation Suite';
    };

    return (
        <div className="atm-studio-wrapper">
            {/* Sidebar Navigation */}
            <div className="atm-sidebar">
                <div className="atm-sidebar-header">
                    <h2>AI Studio</h2>
                    <p>Content Creation Suite</p>
                </div>

                {navigationItems.map((section, sectionIndex) => (
                    <div key={sectionIndex} className="atm-nav-section">
                        <div className="atm-nav-section-title">{section.section}</div>
                        {section.items.map(item => (
                            <button
                                key={item.id}
                                className={`atm-nav-item ${activeView === item.id ? 'active' : ''}`}
                                onClick={() => setActiveView(item.id)}
                            >
                                <span className="atm-nav-icon">{item.icon}</span>
                                {item.label}
                            </button>
                        ))}
                    </div>
                ))}
            </div>

            {/* Main Content Area */}
            <div className="atm-main-content">
                {/* Content Header - only show for non-hub views */}
                {activeView !== 'hub' && (
                    <div className="atm-content-header">
                        <h1 className="atm-content-title">{getPageTitle()}</h1>
                        <p className="atm-content-subtitle">{getPageSubtitle()}</p>
                    </div>
                )}

                {/* Content Body */}
                <div className="atm-content-body">
                    {renderActiveView()}
                </div>
            </div>
        </div>
    );
}

export default App;