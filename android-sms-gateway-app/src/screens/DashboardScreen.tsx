import React, {useState} from 'react';
import {Alert, StyleSheet, Text, View} from 'react-native';
import {Banner, BrandHeader, Button, Card, LinkButton, NavRow, Screen, SectionTitle, Tile, toneColors, type Tone} from '../components/ui';
import {colors, space} from '../components/theme';
import {useGatewayRefresh} from '../hooks/useGateway';
import type {ScreenProps} from '../navigation/types';
import {startGateway, stopGateway, syncNow} from '../services/gatewayController';
import {pauseText} from '../services/gatewayEngine';
import {useGatewayStore} from '../store/gatewayStore';
import {formatRelative} from '../utils/time';

type Status = {tone: Tone; title: string; text: string; action?: {label: string; go: () => void}};

export default function DashboardScreen({navigation}: ScreenProps<'Dashboard'>) {
  useGatewayRefresh();
  const st = useGatewayStore();
  const [busy, setBusy] = useState<'toggle' | 'sync' | null>(null);

  const on = st.running === 'RUNNING' || !!st.service?.desiredRunning;
  const simName = st.selectedSim
    ? `SIM ${st.selectedSim.slotIndex + 1}${st.selectedSim.carrierName ? ` · ${st.selectedSim.carrierName}` : ''}`
    : 'no SIM';

  function status(): Status {
    if (st.connection === 'AUTH_ERROR') {
      return {
        tone: 'danger',
        title: 'Disconnected from portal',
        text: 'The portal no longer accepts this phone (the device was removed or its code changed). Connect it again.',
        action: {label: 'Connect again', go: () => navigation.navigate('Pair')},
      };
    }
    if (st.connection === 'DEVICE_DISABLED') {
      return {tone: 'danger', title: 'Turned off in the portal', text: 'An admin disabled this phone in SMS Center. Ask them to enable it, then turn the gateway on.'};
    }
    if (!st.permissions.sms || !st.permissions.phoneState) {
      return {
        tone: 'danger',
        title: 'Permission needed',
        text: 'Allow the app to send SMS.',
        action: {label: 'Allow', go: () => navigation.navigate('Permissions')},
      };
    }
    if (st.simState === 'MISSING' || st.simState === 'NOT_SELECTED') {
      return {
        tone: 'danger',
        title: st.sims.length ? 'Your SIM is not available' : 'No SIM found',
        text: st.sims.length ? 'Choose which SIM should send the SMS.' : 'Insert a SIM with SMS service.',
        action: st.sims.length ? {label: 'Choose SIM', go: () => navigation.navigate('SimSelection', {fromSettings: true})} : undefined,
      };
    }
    if (!on) {
      return {tone: 'neutral', title: 'Gateway is off', text: `Turn it on and the portal's SMS will go out from ${simName}.`};
    }
    if (st.connection === 'SERVER_OFFLINE') {
      return {tone: 'warning', title: 'Waiting for internet', text: 'The portal cannot be reached right now. The phone keeps trying on its own.'};
    }
    if (st.runtime.pauseReason) {
      return {tone: 'warning', title: 'Paused for now', text: pauseText(st.runtime.pauseReason)};
    }
    return {tone: 'success', title: 'Sending is on', text: `Portal SMS go out from ${simName}.`};
  }

  async function onToggle() {
    setBusy('toggle');
    try {
      if (on) {
        st.setRunning('STOPPING');
        await stopGateway();
      } else {
        st.setRunning('STARTING');
        const r = await startGateway();
        if (!r.ok) {
          st.setRunning('STOPPED');
          Alert.alert('Could not turn on', r.message);
        }
      }
    } finally {
      await st.refresh();
      setBusy(null);
    }
  }

  async function onSync() {
    setBusy('sync');
    try {
      await syncNow();
    } finally {
      await st.refresh();
      setBusy(null);
    }
  }

  const s1 = status();
  const tone = toneColors[s1.tone];
  const blocked = st.connection === 'AUTH_ERROR' || !st.paired;

  return (
    <Screen topInset>
      <BrandHeader right={<LinkButton title="Settings" onPress={() => navigation.navigate('Settings')} />} />

      <Card style={s.hero}>
        <View style={s.heroTop}>
          <View style={[s.ring, {backgroundColor: tone.bg}]}>
            <View style={[s.core, {backgroundColor: tone.fg}]} />
          </View>
          <View style={s.flex}>
            <Text style={s.heroTitle}>{s1.title}</Text>
            <Text style={s.heroText}>{s1.text}</Text>
          </View>
        </View>
        {s1.action ? (
          <Button title={s1.action.label} onPress={s1.action.go} large />
        ) : (
          <Button
            title={busy === 'toggle' ? '' : on ? 'Turn off' : 'Turn on'}
            variant={on ? 'secondary' : 'primary'}
            onPress={onToggle}
            loading={busy === 'toggle'}
            disabled={busy !== null || blocked}
            large
          />
        )}
        <Text style={s.checked}>
          {st.runtime.lastSyncAt ? `Last checked with portal ${formatRelative(st.runtime.lastSyncAt)}` : 'Not checked with the portal yet'}
        </Text>
      </Card>

      {st.counters.pendingAcks > 0 ? (
        <Banner
          tone="info"
          title={`${st.counters.pendingAcks} sent SMS waiting to be confirmed`}
          message="They were sent. The portal will be told as soon as the internet is back. They will not be sent twice."
        />
      ) : null}
      {!st.batteryOptimizationIgnored && on ? (
        <Banner
          tone="warning"
          title="Android may pause the gateway"
          message="Allow background running so SMS keep going with the screen off."
          actionLabel="Fix"
          onAction={() => navigation.navigate('Permissions')}
        />
      ) : null}

      <SectionTitle>Today</SectionTitle>
      <View style={s.tiles}>
        <Tile label="Sent" value={st.counters.sentToday} tone="success" />
        <Tile label="Failed" value={st.counters.failedToday} tone={st.counters.failedToday ? 'danger' : undefined} />
        <Tile label="Waiting to send" value={st.counters.pendingQueue + (st.runtime.queuedOnServer ?? 0)} />
        <Tile label="Last SMS" value={st.counters.lastSmsSentAt ? formatRelative(st.counters.lastSmsSentAt) : '—'} />
      </View>

      <Card style={s.menu}>
        <NavRow title="Send a test SMS" subtitle="Check that this phone can send" onPress={() => navigation.navigate('TestSms')} />
        <NavRow title="Activity" subtitle="Recent messages and events" onPress={() => navigation.navigate('Logs')} />
        <NavRow title="Settings" subtitle={st.pairing ? st.pairing.baseUrl.replace(/^https?:\/\//, '') : undefined} onPress={() => navigation.navigate('Settings')} last />
      </Card>

      <LinkButton title={busy === 'sync' ? 'Checking…' : 'Check for new messages now'} onPress={busy ? () => undefined : onSync} />
    </Screen>
  );
}

const s = StyleSheet.create({
  flex: {flex: 1},
  hero: {paddingVertical: space.xl},
  heroTop: {flexDirection: 'row', alignItems: 'center', gap: space.lg, marginBottom: space.lg},
  ring: {width: 56, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center'},
  core: {width: 22, height: 22, borderRadius: 11},
  heroTitle: {fontSize: 22, fontWeight: '900', color: colors.text},
  heroText: {fontSize: 14, color: colors.muted, marginTop: 4, lineHeight: 20},
  checked: {fontSize: 12, color: colors.faint, textAlign: 'center', marginTop: space.sm},
  tiles: {flexDirection: 'row', flexWrap: 'wrap', gap: space.md, marginBottom: space.md},
  menu: {paddingVertical: space.xs},
});
