const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}
Encore
    .setOutputPath('public/assets/')
    .addEntry('contao-list-widget-bundle', './assets/js/list_widget.js')
    .setPublicPath('/public/assets/')
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()
    .enableSourceMaps(!Encore.isProduction())
// .enableSourceMaps(!Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();