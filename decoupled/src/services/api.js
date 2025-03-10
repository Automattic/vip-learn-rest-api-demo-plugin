const BASE_URL = process.env.REACT_APP_API_URL;

export const fetchUpdates = async (postId) => {
    const url = `${BASE_URL}/liveupdates/v1/posts/${postId}`;
    console.log('Fetching initial updates from:', url);
    
    try {
        const response = await fetch(
            url,
            { 
                credentials: 'include',
                mode: 'cors'
            }
        );
        
        if (!response.ok) {
            throw new Error(`Network response was not ok: ${response.status}`);
        }

        // Debug headers
        console.log('All response headers:');
        response.headers.forEach((value, key) => {
            console.log(`${key}: ${value}`);
        });

        const data = await response.json();
        console.log('Received initial data:', data);
        
        // Get server time from header or use current timestamp as fallback
        const serverTimeHeader = response.headers.get('X-Server-Time');
        console.log('X-Server-Time header value:', serverTimeHeader);
        
        if (!serverTimeHeader) {
            console.log('X-Server-Time header not found, using current timestamp');
        }
        const serverTime = serverTimeHeader 
            ? parseInt(serverTimeHeader, 10)
            : Math.floor(Date.now() / 1000);
            
        console.log('Server time:', serverTime, serverTimeHeader ? '(from header)' : '(current time)');
        
        return { data, serverTime };
    } catch (error) {
        console.error('Error fetching updates:', error);
        throw error;
    }
};

export const fetchUpdatesSince = async (postId, timestamp) => {
    const url = `${BASE_URL}/liveupdates/v1/posts/${postId}/${timestamp}`;
    console.log('Checking for updates since', timestamp, 'at:', url);
    
    try {
        const response = await fetch(
            url,
            { 
                credentials: 'include',
                mode: 'cors'
            }
        );

        if (!response.ok) {
            throw new Error(`Network response was not ok: ${response.status}`);
        }

        // Debug headers
        console.log('All response headers:');
        response.headers.forEach((value, key) => {
            console.log(`${key}: ${value}`);
        });

        const data = await response.json();
        console.log('Received update data:', data);

        // Get server time from header or use current timestamp as fallback
        const serverTimeHeader = response.headers.get('X-Server-Time');
        console.log('X-Server-Time header value:', serverTimeHeader);
        
        if (!serverTimeHeader) {
            console.log('X-Server-Time header not found, using current timestamp');
        }
        const serverTime = serverTimeHeader
            ? parseInt(serverTimeHeader, 10)
            : Math.floor(Date.now() / 1000);
            
        console.log('Server time:', serverTime, serverTimeHeader ? '(from header)' : '(current time)');
        
        return { data, serverTime };
    } catch (error) {
        console.error('Error checking for updates:', error);
        throw error;
    }
}; 