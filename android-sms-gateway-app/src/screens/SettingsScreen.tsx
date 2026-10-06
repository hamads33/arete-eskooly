import React, {useEffect, useState} from 'react';
import {Alert} from 'react-native';
import {Button, Card, Field, InfoRow, LinkButton, NavRow, Paragraph, Screen, SectionTitle, SwitchRow} from '../components/ui';
import {useGatewayRefresh} from '../hooks/useGateway';
import type {ScreenProps} from '../navigation/types';
import {pendingAckCount, saveSettings, unpair} from '../services/gatewayController';
import {effectiveIntervalSeconds} from '../services/gatewayEngine';
import {simLabel} from '../services/simService';
import {prefs} from '../storage/prefs';
import {useGatewayStore} from '../store/gatewayStore';
import type {LocalSettings} from '../types/gateway';

function clampInt(text: string, min: number, max: number, fallback: number): number {
  const n = parseInt(text, 10);
  return Number.isFinite(n) ? Math.max(min, Math.min(max, n)) : fallback;
}

export default function SettingsScreen({navigation}: ScreenProps<'Settings'>) {
  useGatewayRefresh(10_000);
  const st = useGatewayStore();
  const [draft, setDraft] = useState<LocalSettings>(st.settings);
  const [text, setText] = useState({sync: '', delay: '', retention: '', daily: ''});
  const [advanced, setAdvanced] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    prefs.getSettings().then(v => {
      setDraft(v);
      setText({
        sync: String(v.syncIntervalSeconds),
        delay: String(v.smsDelaySeconds),
        retention: String(v.logRetentionDays),
        daily: String(v.dailyLimit),
      });
    });
  }, []);

  async function persist(next: LocalSettings) {
    setDraft(next);
    await saveSettings(next);
  }

  async function onSave() {
    setSaving(true);
    const next: LocalSettings = {
      ...draft,
      syncIntervalSeconds: clampInt(text.sync, 10, 600, draft.syncIntervalSeconds),
      smsDelaySeconds: clampInt(text.delay, 1, 30, draft.smsDelaySeconds),
      logRetentionDays: clampInt(text.retention, 1, 90, draft.logRetentionDays),
      dailyLimit: clampInt(text.daily, 0, 100000, draft.dailyLimit),
    };
    setText({
      sync: String(next.syncIntervalSeconds),
      delay: String(next.smsDelaySeconds),
      retention: String(next.logRetentionDays),
      daily: String(next.dailyLimit),
    });
    await persist(next);
    setSaving(false);
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  }

  async function onDisconnect() {
    const pending = await pendingAckCount();
    Alert.alert(
      'Disconnect this phone?',
      (pending ? `${pending} sent SMS have not been confirmed to the portal yet and will stay unconfirmed. ` : '') +
        'The gateway stops and this phone forgets its portal connection.',
      [
        {text: 'Cancel', style: 'cancel'},
        {
          text: 'Disconnect',
          style: 'destructive',
          onPress: async () => {
            await unpair();
            navigation.reset({index: 0, routes: [{name: 'Pair'}]});
          },
        },
      ],
    );
  }

  const cfg = st.serverConfig;

  return (
    <Screen>
      <SectionTitle>Portal</SectionTitle>
      <Card>
        <InfoRow label="Connected to" value={st.pairing ? st.pairing.baseUrl.replace(/^https?:\/\//, '') : '—'} />
        <InfoRow label="Device name" value={st.pairing?.deviceName ?? '—'} />
        <NavRow title="Connect again" subtitle="Use a new pairing code" onPress={() => navigation.navigate('Pair')} last />
      </Card>

      <SectionTitle>Sending</SectionTitle>
      <Card>
        <NavRow
          title="SIM card"
          subtitle={st.selectedSim ? simLabel(st.selectedSim) : 'Not chosen'}
          onPress={() => navigation.navigate('SimSelection', {fromSettings: true})}
        />
        <SwitchRow
          label="Turn on after phone restarts"
          help="If the gateway was on when the phone switched off."
          value={draft.startOnBoot}
          onValueChange={v => persist({...draft, startOnBoot: v})}
        />
        <SwitchRow
          label="Delivery reports"
          help="Show when the carrier confirms delivery. Not all networks support it."
          value={draft.requestDeliveryReports}
          onValueChange={v => persist({...draft, requestDeliveryReports: v})}
        />
      </Card>

      <LinkButton title={advanced ? 'Hide advanced settings' : 'Show advanced settings'} onPress={() => setAdvanced(v => !v)} />
      {advanced ? (
        <>
          <Card>
            <Field
              label="Check for messages every (seconds)"
              keyboardType="number-pad"
              value={text.sync}
              onChangeText={v => setText(t => ({...t, sync: v}))}
              help={`Currently every ${effectiveIntervalSeconds(draft, cfg)} s. The portal sets the minimum (${cfg?.poll_interval_seconds ?? '—'} s).`}
            />
            <Field
              label="Wait between SMS (seconds)"
              keyboardType="number-pad"
              value={text.delay}
              onChangeText={v => setText(t => ({...t, delay: v}))}
              help="Mobile networks block fast bursts. 2–5 seconds is safe."
            />
            <Field
              label="Daily SMS limit on this phone"
              keyboardType="number-pad"
              value={text.daily}
              onChangeText={v => setText(t => ({...t, daily: v}))}
              help="0 means no limit. Useful if your SIM package has a daily cap."
            />
            <Field
              label="Keep activity for (days)"
              keyboardType="number-pad"
              value={text.retention}
              onChangeText={v => setText(t => ({...t, retention: v}))}
            />
            <Button title={saved ? 'Saved' : 'Save'} onPress={onSave} loading={saving} />
          </Card>
          <SectionTitle>Set in the portal</SectionTitle>
          <Card>
            {cfg ? (
              <>
                <InfoRow label="SMS sending" value={cfg.sms_enabled && cfg.queue_processing_enabled ? 'On' : 'Off'} />
                <InfoRow label="Messages per batch" value={cfg.batch_size} />
                <InfoRow label="Quiet hours" value={cfg.quiet_hours ? `${cfg.quiet_hours.start}–${cfg.quiet_hours.end}` : 'Off'} />
                <InfoRow label="Reservation timeout" value={`${cfg.reservation_timeout_minutes} min`} />
              </>
            ) : (
              <Paragraph muted>Not loaded yet.</Paragraph>
            )}
          </Card>
        </>
      ) : null}

      <Card>
        <NavRow title="About this app" onPress={() => navigation.navigate('About')} last />
      </Card>
      <Button title="Disconnect this phone" variant="ghost" onPress={onDisconnect} />
    </Screen>
  );
}
