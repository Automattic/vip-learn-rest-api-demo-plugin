const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'post-selector/index': './src/post-selector/index.js',
    },
}; 