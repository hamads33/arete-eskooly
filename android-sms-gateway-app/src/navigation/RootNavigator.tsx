import React from 'react';
import {DefaultTheme, NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {colors} from '../components/theme';
import AboutScreen from '../screens/AboutScreen';
import DashboardScreen from '../screens/DashboardScreen';
import LogsScreen from '../screens/LogsScreen';
import PairScreen from '../screens/PairScreen';
import PermissionsScreen from '../screens/PermissionsScreen';
import SettingsScreen from '../screens/SettingsScreen';
import SimSelectionScreen from '../screens/SimSelectionScreen';
import SplashScreen from '../screens/SplashScreen';
import TestSmsScreen from '../screens/TestSmsScreen';
import type {RootStackParamList} from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

const theme = {
  ...DefaultTheme,
  colors: {...DefaultTheme.colors, background: colors.bg, card: colors.bg, text: colors.text, primary: colors.primary},
};

export default function RootNavigator() {
  return (
    <NavigationContainer theme={theme}>
      <Stack.Navigator
        initialRouteName="Splash"
        screenOptions={{
          headerShadowVisible: false,
          headerStyle: {backgroundColor: colors.bg},
          headerTintColor: colors.primary,
          headerTitleStyle: {fontWeight: '800', color: colors.text},
          animation: 'slide_from_right',
        }}>
        <Stack.Screen name="Splash" component={SplashScreen} options={{headerShown: false, animation: 'none'}} />
        <Stack.Screen name="Pair" component={PairScreen} options={{headerShown: false}} />
        <Stack.Screen name="Permissions" component={PermissionsScreen} options={{headerShown: false}} />
        <Stack.Screen name="SimSelection" component={SimSelectionScreen} options={{headerShown: false}} />
        <Stack.Screen name="Dashboard" component={DashboardScreen} options={{headerShown: false}} />
        <Stack.Screen name="TestSms" component={TestSmsScreen} options={{title: 'Send a test SMS'}} />
        <Stack.Screen name="Logs" component={LogsScreen} options={{title: 'Activity'}} />
        <Stack.Screen name="Settings" component={SettingsScreen} options={{title: 'Settings'}} />
        <Stack.Screen name="About" component={AboutScreen} options={{title: 'About'}} />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
