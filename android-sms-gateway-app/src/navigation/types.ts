import type {NativeStackScreenProps} from '@react-navigation/native-stack';

export type RootStackParamList = {
  Splash: undefined;
  Pair: undefined;
  Permissions: {next?: 'SimSelection' | 'Dashboard'} | undefined;
  SimSelection: {fromSettings?: boolean} | undefined;
  Dashboard: undefined;
  TestSms: undefined;
  Logs: undefined;
  Settings: undefined;
  About: undefined;
};

export type ScreenProps<K extends keyof RootStackParamList> = NativeStackScreenProps<RootStackParamList, K>;
