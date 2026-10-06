import React, {useEffect, useMemo, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {Banner, Button, Card, Field, Paragraph, Screen, SectionTitle} from '../components/ui';
import {colors, radius, space} from '../components/theme';
import type {SimInfo} from '../native/SmsGateway';
import {listSims, simLabel} from '../services/simService';
import {sendTestSms} from '../services/testSms';
import {prefs} from '../storage/prefs';

/** GSM-7 basic charset; anything else (e.g. Urdu) forces UCS-2 (70/67 chars per part). */
const GSM7 = /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\[~\]|€]*$/;

function segmentEstimate(text: string): {parts: number; encoding: string} {
  if (!text) {
    return {parts: 0, encoding: 'GSM'};
  }
  if (GSM7.test(text)) {
    const len = [...text].reduce((n, ch) => n + ('^{}\\[~]|€'.includes(ch) ? 2 : 1), 0);
    return {parts: len <= 160 ? 1 : Math.ceil(len / 153), encoding: 'GSM'};
  }
  const len = [...text].length;
  return {parts: len <= 70 ? 1 : Math.ceil(len / 67), encoding: 'Unicode'};
}

type Status = {state: 'idle'} | {state: 'sending'} | {state: 'sent'; segments: number} | {state: 'failed'; message: string};

export default function TestSmsScreen() {
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState('Test message from the LMS SMS gateway.');
  const [sims, setSims] = useState<SimInfo[]>([]);
  const [simId, setSimId] = useState<number | null>(null);
  const [status, setStatus] = useState<Status>({state: 'idle'});

  useEffect(() => {
    (async () => {
      const [res, selected] = await Promise.all([listSims(), prefs.getSelectedSim()]);
      setSims(res.sims);
      const pick = res.sims.find(s => s.subscriptionId === selected?.subscriptionId) ?? res.sims[0];
      setSimId(pick ? pick.subscriptionId : null);
    })();
  }, []);

  const estimate = useMemo(() => segmentEstimate(message), [message]);
  const phoneOk = /^\+?[0-9][0-9\s-]{6,19}$/.test(phone.trim());

  async function onSend() {
    if (simId === null) {
      return;
    }
    setStatus({state: 'sending'});
    const r = await sendTestSms(phone.trim(), message, simId);
    setStatus(r.ok ? {state: 'sent', segments: r.segments} : {state: 'failed', message: r.message});
  }

  return (
    <Screen>
      <Paragraph muted>Sends one SMS straight from this phone to check it works. It is not reported to the portal.</Paragraph>
      <Card>
        <Field
          label="Phone Number"
          placeholder="+923001234567"
          keyboardType="phone-pad"
          value={phone}
          onChangeText={setPhone}
          error={phone && !phoneOk ? 'Enter a full number, e.g. +923001234567' : null}
        />
        <Field
          label="Message"
          value={message}
          onChangeText={setMessage}
          multiline
          help={`${[...message].length} characters · ${estimate.encoding} · ${estimate.parts} part${estimate.parts === 1 ? '' : 's'}`}
        />
        <SectionTitle>SIM</SectionTitle>
        {sims.length === 0 ? <Banner tone="danger" title="No active SIM detected." /> : null}
        <View style={s.sims}>
          {sims.map(sim => (
            <Pressable
              key={sim.subscriptionId}
              onPress={() => setSimId(sim.subscriptionId)}
              accessibilityRole="radio"
              accessibilityState={{selected: sim.subscriptionId === simId}}
              style={[s.sim, sim.subscriptionId === simId && s.simOn]}>
              <Text style={[s.simText, sim.subscriptionId === simId && s.simTextOn]}>{simLabel(sim)}</Text>
            </Pressable>
          ))}
        </View>
      </Card>
      {status.state === 'sending' ? <Banner tone="info" title="Sending…" message="Waiting for the phone network to accept it." /> : null}
      {status.state === 'sent' ? (
        <Banner tone="success" title="Sent" message={`The SMS left this phone${status.segments > 1 ? ` in ${status.segments} parts` : ''}. Check the other phone.`} />
      ) : null}
      {status.state === 'failed' ? <Banner tone="danger" title="Failed" message={status.message} /> : null}
      <Button
        title="Send test SMS"
        onPress={onSend}
        large
        loading={status.state === 'sending'}
        disabled={!phoneOk || !message || simId === null || status.state === 'sending'}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  sims: {gap: space.sm},
  sim: {borderWidth: 1.5, borderColor: colors.border, borderRadius: radius.md, padding: space.md},
  simOn: {borderColor: colors.primary, backgroundColor: colors.primaryBg},
  simText: {fontSize: 15, color: colors.text},
  simTextOn: {fontWeight: '700', color: colors.primary},
});
