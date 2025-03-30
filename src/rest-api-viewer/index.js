import { render } from '@wordpress/element';
import { useState, useEffect } from '@wordpress/element';
import { TextControl, Button, Panel, PanelBody, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import './style.css';

const ResponseViewer = () => {
    // Get initial route from URL query parameter
    const getInitialRoute = () => {
        const params = new URLSearchParams(window.location.search);
        const routeParam = params.get('route');
        return routeParam || '/wp-json/wp/v2/posts?status=publish';
    };

    const [route, setRoute] = useState(getInitialRoute());
    const [response, setResponse] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    // Update URL when route changes
    useEffect(() => {
        const url = new URL(window.location);
        url.searchParams.set('route', route);
        window.history.replaceState({}, '', url);
    }, [route]);

    // Auto-fetch on initial load if route is provided in URL
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('route')) {
            fetchResponse();
        }
    }, []); // Empty dependency array means this runs once on mount

    const validateRoute = (route) => {
        // Ensure route starts with /wp-json/wp/
        const validPrefix = /^\/wp-json\/wp\//;
        if (!validPrefix.test(route)) {
            throw new Error(__('Invalid route. Route must start with /wp-json/wp/', 'live-updates'));
        }
        return route;
    };

    const handleRouteChange = (newRoute) => {
        setError(null);
        try {
            validateRoute(newRoute);
            setRoute(newRoute);
        } catch (err) {
            setError(err.message);
        }
    };

    const fetchResponse = async () => {
        setLoading(true);
        setError(null);
        try {
            validateRoute(route);
            // Remove /wp-json prefix if present
            const path = route.replace(/^\/wp-json/, '');
            const result = await apiFetch({ path });
            setResponse(result);
        } catch (err) {
            setError(err.message);
            setResponse(null);
        }
        setLoading(false);
    };

    return (
        <div className="rest-api-viewer">
            {error && (
                <Notice 
                    status="error"
                    isDismissible={false}
                    className="rest-api-viewer__notice"
                >
                    {error}
                </Notice>
            )}

            <div className="rest-api-viewer__controls">
                <TextControl
                    label={__('WordPress REST API Route', 'live-updates')}
                    help={__('Enter a WordPress REST API route (must start with /wp-json/wp/)', 'live-updates')}
                    value={route}
                    onChange={handleRouteChange}
                    className="rest-api-viewer__route-input"
                />
                <Button 
                    variant="primary"
                    onClick={fetchResponse}
                    isBusy={loading}
                    disabled={loading || error}
                >
                    {__('Fetch Response', 'live-updates')}
                </Button>
            </div>

            {response && (
                <Panel className="rest-api-viewer__response">
                    <PanelBody title={__('Response', 'live-updates')} initialOpen={true}>
                        <pre>
                            {JSON.stringify(response, null, 2)}
                        </pre>
                    </PanelBody>
                </Panel>
            )}
        </div>
    );
};

// Initialize the viewer
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('rest-api-viewer-root');
    if (root) {
        render(<ResponseViewer />, root);
    }
}); 