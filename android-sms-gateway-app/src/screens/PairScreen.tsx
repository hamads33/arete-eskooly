import React, {useState} from 'react';
import {Alert, StyleSheet, Text, View} from 'react-native';
import {Banner, BrandHeader, Button, Card, Field, Hero, LinkButton, Screen, Steps, TrustNote} from '../components/ui';
import {colors, DEFAULT_SERVER_URL, radius, space} from '../components/theme';
import type {ScreenProps} from '../navigation/types';
import {
  checkServer,
  describeApiError,
  pairWithCode,
  pairWithToken,
  pendingAckCount,
  stopGateway,
} from '../services/gatewayController';
import {hasRequiredPermissions} from '../services/permissions';
import {resolveSelectedSim} from '../services/simService';
import {useGatewayStore} from '../store/gatewayStore';

/** ABCD-EFGH-JKLM as the admin sees it in the portal. */
function formatCode(raw: string): string {
  const clean = raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
  return clean.match(/.{1,4}/g)?.join('-') ?? '';
}

export default function PairScreen({navigation}: ScreenProps<'Pair'>) {
  const existing = useGatewayStore(s => s.pairing);
  const [url, setUrl] = useState(existing?.baseUrl ?? DEFAULT_SERVER_URL);
  const [editUrl, setEditUrl] = useState(false);
  const [useToken, setUseToken] = useState(false);
  const [code, setCode] = useState('');
  const [token, setToken] = useState('');
  const [busy, setBusy] = useState<'test' | 'connect' | null>(null);
  const [result, setResult] = useState<{tone: 'success' | 'danger'; text: string} | null>(null);

  async function onTest() {
    setBusy('test');
    setResult(null);
    try {
      await checkServer(url);
      setResult({tone: 'success', text: 'The portal is reachable.'});
    } catch (e) {
      setResult({tone: 'danger', text: describeApiError(e)});
    } finally {
      setBusy(null);
    }
  }

  async function doConnect() {
    setBusy('connect');
    setResult(null);
    try {
      if (existing) {
        // The old token keeps working until the new pairing succeeds; only stop using it now.
        await stopGateway().catch(() => undefined);
      }
      if (useToken) {
        await pairWithToken(url, token, '');
      } else {
        await pairWithCode(url, code, '');
      }
      setToken('');
      setCode('');
      await useGatewayStore.getState().refresh();
      const next = !(await hasRequiredPermissions())
        ? 'Permissions'
        : (await resolveSelectedSim()).state !== 'AVAILABLE'
        ? 'SimSelection'
        : 'Dashboard';
      navigation.reset({index: 0, routes: [{name: next}]});
    } catch (e) {
      setResult({tone: 'danger', text: describeApiError(e)});
    } finally {
      setBusy(null);
    }
  }

  async function onConnect() {
    if (existing && (await pendingAckCount()) > 0) {
      Alert.alert(
        'Some SMS are not confirmed yet',
        'This phone sent SMS that the portal has not heard about yet. Connecting again may leave them unconfirmed. Continue?',
        [
          {text: 'Cancel', style: 'cancel'},
          {text: 'Connect again', style: 'destructive', onPress: doConnect},
        ],
      );
      return;
    }
    doConnect();
  }

  const codeReady = code.replace(/-/g, '').length === 12;
  const canConnect = url.trim() !== '' && (useToken ? token.trim() !== '' : codeReady);
  const host = url.replace(/^https?:\/\//, '').replace(/\/.*$/, '');

  return (
    <Screen topInset>
      <BrandHeader />
      {!existing ? <Steps current={1} /> : null}
      <Hero
        eyebrow="CONNECT THIS PHONE"
        title={existing ? 'Connect again' : 'Link to your portal'}
        subtitle={
          useToken
            ? 'Paste the device token an admin generated in the portal.'
            : 'In the portal open SMS Center → Gateway Devices, tap Register Device, and type the code shown there.'
        }
      />
      <Card>
        <View style={s.server}>
          <View style={s.flex}>
            <Text style={s.serverLabel}>Portal</Text>
            <Text style={s.serverHost} numberOfLines={1}>
              {host || 'Not set'}
            </Text>
          </View>
          <LinkButton title={editUrl ? 'Done' : 'Change'} onPress={() => setEditUrl(v => !v)} />
        </View>
        {editUrl ? (
          <>
            <Field
              label="Portal address"
              placeholder="https://school.example.com"
              value={url}
              onChangeText={setUrl}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="url"
              help={__DEV__ ? 'Debug build: http:// is allowed for local testing.' : 'Must start with https://'}
            />
            <Button title="Check connection" variant="secondary" onPress={onTest} loading={busy === 'test'} disabled={!url.trim() || !!busy} />
          </>
        ) : null}

        {useToken ? (
          <Field
            label="Device token"
            placeholder="smsgw_…"
            value={token}
            onChangeText={setToken}
            autoCapitalize="none"
            autoCorrect={false}
            secureTextEntry
          />
        ) : (
          <Field
            label="Pairing code"
            placeholder="XXXX-XXXX-XXXX"
            value={code}
            onChangeText={v => setCode(formatCode(v))}
            autoCapitalize="characters"
            autoCorrect={false}
            maxLength={14}
            style={s.code}
            help="Valid for 30 minutes and can be used once."
          />
        )}

        {result ? (
          <Banner tone={result.tone} title={result.tone === 'success' ? 'All good' : 'Could not connect'} message={result.text} />
        ) : null}
        <Button title="Connect" onPress={onConnect} loading={busy === 'connect'} disabled={!canConnect || !!busy} large />
        <LinkButton
          title={useToken ? 'Use a pairing code instead' : 'Have a device token instead?'}
          onPress={() => {
            setUseToken(v => !v);
            setResult(null);
          }}
        />
      </Card>
      {existing && navigation.canGoBack() ? <Button title="Cancel" variant="ghost" onPress={() => navigation.goBack()} /> : null}
      <TrustNote>Your connection is encrypted and stored only on this phone.</TrustNote>
    </Screen>
  );
}

const s = StyleSheet.create({
  flex: {flex: 1},
  server: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary50,
    borderRadius: radius.md,
    paddingHorizontal: space.md,
    paddingVertical: space.xs,
    marginBottom: space.lg,
  },
  serverLabel: {fontSize: 11, fontWeight: '800', color: colors.primary, letterSpacing: 1.2, textTransform: 'uppercase'},
  serverHost: {fontSize: 15, fontWeight: '700', color: colors.text, marginTop: 2},
  code: {fontSize: 22, fontWeight: '800', letterSpacing: 3, textAlign: 'center'},
});
