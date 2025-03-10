import { useState, useEffect, useCallback } from 'react';
import { fetchUpdates, fetchUpdatesSince } from '../services/api';
import UpdateItem from './UpdateItem';

const BASE_INTERVAL = parseInt(process.env.REACT_APP_UPDATE_INTERVAL, 10) || 30000;
const MAX_JITTER = parseInt(process.env.REACT_APP_UPDATE_JITTER, 10) || 20000;

const LiveUpdates = ({ postId }) => {
    console.log('LiveUpdates component mounted with postId:', postId);
    const [updates, setUpdates] = useState([]);
    const [lastCheck, setLastCheck] = useState(null);
    const [nextCheckInterval, setNextCheckInterval] = useState(null);
    const [error, setError] = useState(null);

    const calculateNextInterval = () => {
        const jitter = Math.floor(Math.random() * MAX_JITTER);
        const interval = BASE_INTERVAL + jitter;
        console.log('Next check scheduled in:', interval, 'ms');
        return interval;
    };

    const loadInitialUpdates = useCallback(async () => {
        console.log('Loading initial updates for postId:', postId);
        try {
            setError(null);
            const { data, serverTime } = await fetchUpdates(postId);
            console.log('Setting initial updates:', data);
            setUpdates(data);
            setLastCheck(serverTime);
            setNextCheckInterval(calculateNextInterval());
        } catch (err) {
            console.error('Failed to load initial updates:', err);
            setError('Failed to load updates');
        }
    }, [postId]);

    const checkForNewUpdates = useCallback(async () => {
        if (!lastCheck) {
            console.log('No lastCheck timestamp, skipping update check');
            return;
        }

        console.log('Checking for new updates since:', lastCheck);
        try {
            setError(null);
            const { data, serverTime } = await fetchUpdatesSince(postId, lastCheck);
            
            if (data.length > 0) {
                console.log('Found new updates:', data);
                setUpdates(prevUpdates => {
                    const existingIds = new Set(prevUpdates.map(update => update.id));
                    const newUpdates = data.filter(update => !existingIds.has(update.id));
                    return [...newUpdates, ...prevUpdates];
                });
            } else {
                console.log('No new updates found');
            }
            
            setLastCheck(serverTime);
            setNextCheckInterval(calculateNextInterval());
        } catch (err) {
            console.error('Failed to check for new updates:', err);
            setError('Failed to check for new updates');
        }
    }, [postId, lastCheck]);

    // Initial load
    useEffect(() => {
        console.log('Initial load effect triggered');
        loadInitialUpdates();
    }, [loadInitialUpdates]);

    // Polling
    useEffect(() => {
        if (nextCheckInterval) {
            console.log('Setting up polling timer for:', nextCheckInterval, 'ms');
            const timer = setTimeout(checkForNewUpdates, nextCheckInterval);
            return () => {
                console.log('Cleaning up polling timer');
                clearTimeout(timer);
            };
        }
    }, [nextCheckInterval, checkForNewUpdates]);

    if (error) {
        return <div className="error">{error}</div>;
    }

    if (!updates.length) {
        return <p>No updates found.</p>;
    }

    return (
        <div className="live-updates">
            {updates.map(update => (
                <UpdateItem key={update.id} update={update} />
            ))}
        </div>
    );
};

export default LiveUpdates; 