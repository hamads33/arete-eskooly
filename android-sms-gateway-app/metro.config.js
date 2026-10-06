const fs = require('fs');
const {getDefaultConfig, mergeConfig} = require('@react-native/metro-config');

// On Windows the release build may run from a short `subst` drive (see README) to stay under
// the 260-character path limit of the native build. Metro resolves files to their real path,
// so that real location must be watched too. On a normal checkout this adds nothing.
const realRoot = fs.realpathSync.native(__dirname);

/**
 * Metro configuration
 * https://reactnative.dev/docs/metro
 *
 * @type {import('@react-native/metro-config').MetroConfig}
 */
const config = {
  watchFolders: realRoot !== __dirname ? [realRoot] : [],
};

module.exports = mergeConfig(getDefaultConfig(__dirname), config);
