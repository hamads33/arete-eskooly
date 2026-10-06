/**
 * @format
 */

import {AppRegistry} from 'react-native';
import App from './App';
import {name as appName} from './app.json';
import {headlessMaintenance, headlessTick} from './src/services/gatewayController';

AppRegistry.registerComponent(appName, () => App);

// Driven by GatewayForegroundService every sync interval while the gateway is on.
AppRegistry.registerHeadlessTask('GatewayTick', () => headlessTick);
// Driven by WorkManager (~15 min) while the gateway is off: acknowledgments only, never sends.
AppRegistry.registerHeadlessTask('GatewayMaintenance', () => headlessMaintenance);
