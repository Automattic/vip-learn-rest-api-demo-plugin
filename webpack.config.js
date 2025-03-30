const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'post-selector/index': './src/post-selector/index.js',
        'live-updates-block/index': './src/live-updates-block/index.js',
        'live-updates-frontend/index': './src/live-updates-frontend/index.js',
        'rest-api-viewer/index': './src/rest-api-viewer/index.js',
    },
}; 