import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
// --- THIS IS THE FIX: Replaced 'embed' with 'plus' ---
import { external, link, plus } from '@wordpress/icons';

function VideoResult({ video, onEmbed }) {
    const [copied, setCopied] = useState(false);

    const copyToClipboard = () => {
        navigator.clipboard.writeText(video.url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="atm-video-result-item">
            <div className="atm-video-thumbnail">
                <img src={video.thumbnail} alt={video.title} />
            </div>
            <div className="atm-video-details">
                <h4 className="atm-video-title">{video.title}</h4>
                <p className="atm-video-description">{video.description}</p>
                <div className="atm-video-meta">
                    <span>{video.channel}</span> • <span>{video.date}</span>
                </div>
                <div className="atm-video-actions">
                    <Button icon={external} href={video.url} target="_blank">Watch</Button>
                    <Button icon={link} onClick={copyToClipboard}>
                        {copied ? 'Copied!' : 'Copy Link'}
                    </Button>
                    {/* --- THIS IS THE FIX: Use the imported 'plus' icon --- */}
                    <Button icon={plus} onClick={() => onEmbed(video.url)} className="is-embed">Embed</Button>
                </div>
            </div>
        </div>
    );
}

export default VideoResult;