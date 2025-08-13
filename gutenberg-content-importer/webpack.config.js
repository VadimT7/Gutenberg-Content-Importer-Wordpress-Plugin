const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        admin: './assets/js/admin.js',
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/dist'),
        filename: '[name].js',
    },
    externals: {
        jquery: 'jQuery',
        wp: 'wp',
    },
};

