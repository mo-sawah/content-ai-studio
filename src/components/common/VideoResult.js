import { Button } from '@wordpress/components';
import { external, link, embed } from '@wordpress/icons';

function VideoResult({ video, onEmbed }) {
    const copyToClipboard = () => {
        navigator.clipboard.writeText(video.url);
        // You could add a small "Copied!" notification here
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
                    <Button icon={link} onClick={copyToClipboard}>Copy Link</Button>
                    <Button icon={embed} isPrimary onClick={() => onEmbed(video.url)}>Embed</Button>
                </div>
            </div>
        </div>
    );
}

export default VideoResult;