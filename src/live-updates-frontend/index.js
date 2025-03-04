import { render } from '@wordpress/element';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const BASE_INTERVAL = 30000; // 30 seconds
const MAX_JITTER = 20000;   // 20 seconds

const LiveUpdates = ({ postId }) => {
    const [updates, setUpdates] = useState([]);
    const [lastCheck, setLastCheck] = useState(null);
    const [nextCheckInterval, setNextCheckInterval] = useState(null);

    const calculateNextInterval = () => {
        const jitter = Math.floor(Math.random() * MAX_JITTER);
        const interval = BASE_INTERVAL + jitter;
        console.log('Next check interval:', interval, 'ms');
        return interval;
    };

    const fetchUpdates = useCallback(async () => {
        try {
            const response = await apiFetch({
                path: `liveupdates/v1/posts/${postId}`,
                parse: false, // Get raw response to access headers
            });
            
            const serverTime = parseInt(response.headers.get('X-Server-Time'), 10);
            const data = await response.json();
            
            setUpdates(data);
            setLastCheck(serverTime);
            setNextCheckInterval(calculateNextInterval());
        } catch (error) {
            console.error('Error fetching updates:', error);
        }
    }, [postId]);

    const checkForNewUpdates = useCallback(async () => {
        if (!lastCheck || !nextCheckInterval) return;
        
        try {
            const response = await apiFetch({
                path: `liveupdates/v1/posts/${postId}/${lastCheck}`,
                parse: false,
            });
            
            const serverTime = parseInt(response.headers.get('X-Server-Time'), 10);
            const data = await response.json();
            
            if (data.length > 0) {
                setUpdates(prevUpdates => {
                    const existingIds = new Set(prevUpdates.map(update => update.id));
                    const newUpdates = data.filter(update => !existingIds.has(update.id));
                    return [...newUpdates, ...prevUpdates];
                });
            }
            
            setLastCheck(serverTime);
            setNextCheckInterval(calculateNextInterval());
        } catch (error) {
            console.error('Error checking for updates:', error);
        }
    }, [postId, lastCheck, nextCheckInterval]);

    // Initial fetch
    useEffect(() => {
        if (postId) {
            fetchUpdates();
        }
    }, [postId, fetchUpdates]);

    // Polling setup
    useEffect(() => {
        if (postId && nextCheckInterval) {
            const timer = setTimeout(checkForNewUpdates, nextCheckInterval);
            return () => clearTimeout(timer);
        }
    }, [postId, nextCheckInterval, checkForNewUpdates]);

    if (!updates.length) {
        return <p>{__('No updates found.', 'live-updates')}</p>;
    }

    return (
        <div className="live-updates">
            {updates.map((update) => (
                <article key={update.id} className="live-update-item">
                    <header>
                        <h3 dangerouslySetInnerHTML={{ __html: update.title.rendered }} />
                        <time dateTime={update.date}>{new Date(update.date).toLocaleString()}</time>
                    </header>
                    <div dangerouslySetInnerHTML={{ __html: update.content.rendered }} />
                </article>
            ))}
        </div>
    );
};

// Initialize all live update blocks on the page
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wp-block-live-updates-display').forEach((element) => {
        const postId = element.dataset.postId;
        if (postId) {
            render(<LiveUpdates postId={postId} />, element);
        }
    });
}); 