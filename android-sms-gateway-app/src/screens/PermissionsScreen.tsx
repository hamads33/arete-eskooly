import React, {useCallback, useEffect, useState} from 'react';
import {AppState, StyleSheet, Text, View} from 'react-native';
import {Banner, BrandHeader, Button, Card, Hero, Pill, Screen, Steps} from '../components/ui';
import {colors, space} from '../components/theme';
import SmsGateway from '../native/SmsGateway';
import type {ScreenProps} from '../navigation/types';
import {openAppSettings, permissionSummary, requestRequired, type PermissionResult} from '../services/permissions';
import {resolveSelectedSim} from '../services/simService';
import {useGatewayStore} from '../store/gatewayStore';

function Item({title, text, ok}: {title: string; text: string; ok: boolean}) {
  return (
    <View style={s.item}>
      <View style={[s.check, ok && s.checkOn]}>
        <Text style={[s.checkMark, ok && s.checkMarkOn]}>{ok ? '✓' : ''}</Text>
      </View>
      <View style={s.flex}>
        <Text style={s.itemTitle}>{title}</Text>
        <Text style={s.itemText}>{text}</Text>
      </View>
    </View>
  );
}

export default function PermissionsScreen({navigation}: ScreenProps<'Permissions'>) {
  const [granted, setGranted] = useState({sms: false, phoneState: false});
  const [result, setResult] = useState<PermissionResult | null>(null);
  const [battery, setBattery] = useState(false);
  const paired = useGatewayStore(st => st.paired);

  const reload = useCallback(async () => {
    setGranted(await permissionSummary());
    setBattery(await SmsGateway.isIgnoringBatteryOptimizations().catch(() => false));
  }, []);

  useEffect(() => {
    reload();
    const sub = AppState.addEventListener('change', st => st === 'active' && reload());
    return () => sub.remove();
  }, [reload]);

  async function onAllow() {
    setResult(await requestRequired());
    await reload();
  }

  async function onContinue() {
    await useGatewayStore.getState().refresh();
    const sim = await resolveSelectedSim();
    navigation.reset({index: 0, routes: [{name: sim.state === 'AVAILABLE' ? 'Dashboard' : 'SimSelection'}]});
  }

  const ready = granted.sms && granted.phoneState;

  return (
    <Screen topInset>
      <BrandHeader />
      {paired ? <Steps current={2} /> : null}
      <Hero
        eyebrow="ALMOST THERE"
        title="Allow SMS sending"
        subtitle="This phone will send the portal's messages (absence alerts, fee reminders) from its SIM."
      />
      <Card>
        <Item title="Send SMS" text="Only outgoing SMS from the portal. Your inbox is never read." ok={granted.sms} />
        <Item title="See SIM cards" text="So you can pick SIM 1 or SIM 2. No calls or IMEI are read." ok={granted.phoneState} />
        {result === 'blocked' && !ready ? (
          <>
            <Banner tone="warning" title="Turned off in settings" message="Open App settings → Permissions and allow SMS and Phone." />
            <Button title="Open App settings" variant="secondary" onPress={openAppSettings} />
          </>
        ) : null}
        {result === 'denied' && !ready ? (
          <Banner tone="warning" title="Permission needed" message="The gateway cannot send SMS without it." />
        ) : null}
        {!ready && result !== 'blocked' ? <Button title="Allow" onPress={onAllow} large /> : null}
      </Card>

      <Card>
        <View style={s.batteryHead}>
          <Text style={s.itemTitle}>Keep running in the background</Text>
          <Pill label={battery ? 'On' : 'Recommended'} tone={battery ? 'success' : 'warning'} />
        </View>
        <Text style={s.itemText}>Stops Android from pausing the gateway when the screen is off. Keep the phone on a charger.</Text>
        {!battery ? (
          <Button title="Allow background running" variant="secondary" onPress={() => SmsGateway.requestIgnoreBatteryOptimizations()} style={s.mt} />
        ) : null}
      </Card>

      <Button title="Continue" onPress={onContinue} disabled={!ready} large />
    </Screen>
  );
}

const s = StyleSheet.create({
  flex: {flex: 1},
  mt: {marginTop: space.md},
  item: {flexDirection: 'row', gap: space.md, marginBottom: space.lg},
  check: {
    width: 26,
    height: 26,
    borderRadius: 13,
    borderWidth: 2,
    borderColor: colors.borderStrong,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  checkOn: {backgroundColor: colors.success, borderColor: colors.success},
  checkMark: {fontSize: 14, fontWeight: '900', color: colors.faint},
  checkMarkOn: {color: '#fff'},
  itemTitle: {fontSize: 16, fontWeight: '800', color: colors.text},
  itemText: {fontSize: 14, color: colors.muted, marginTop: 3, lineHeight: 20},
  batteryHead: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm},
});
