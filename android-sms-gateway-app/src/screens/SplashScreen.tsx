import React, {useEffect, useState} from 'react';
import {ActivityIndicator, Image, StyleSheet, Text, View} from 'react-native';
import {Button} from '../components/ui';
import {colors} from '../components/theme';
import type {ScreenProps} from '../navigation/types';
import {bootstrap} from '../services/gatewayController';
import {useGatewayStore} from '../store/gatewayStore';

export default function SplashScreen({navigation}: ScreenProps<'Splash'>) {
  const [error, setError] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const route = await bootstrap();
        await useGatewayStore.getState().refresh();
        if (!cancelled) {
          navigation.reset({index: 0, routes: [{name: route}]});
        }
      } catch {
        if (!cancelled) {
          setError('The local gateway database could not be opened.');
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [navigation, attempt]);

  return (
    <View style={s.root}>
      <Image source={require('../assets/arete-logo.png')} style={s.logo} resizeMode="contain" accessibilityIgnoresInvertColors />
      <Text style={s.title}>Arete-SMS</Text>
      <Text style={s.subtitle}>Secure SMS bridge for the LMS</Text>
      {error ? (
        <View style={s.error}>
          <Text style={s.errorText}>{error}</Text>
          <Button title="Try again" onPress={() => {
            setError(null);
            setAttempt(a => a + 1);
          }} />
        </View>
      ) : (
        <ActivityIndicator style={s.spinner} color={colors.primary} />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  root: {flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.bg, padding: 24},
  logo: {width: 144, height: 144, marginBottom: 20},
  title: {fontSize: 26, fontWeight: '800', color: colors.text},
  subtitle: {fontSize: 15, color: colors.muted, marginTop: 6},
  spinner: {marginTop: 32},
  error: {marginTop: 32, alignSelf: 'stretch'},
  errorText: {color: colors.danger, textAlign: 'center', marginBottom: 12, fontSize: 15},
});
