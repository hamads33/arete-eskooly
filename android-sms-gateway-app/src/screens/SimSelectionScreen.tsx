import React, {useCallback, useEffect, useState} from 'react';
import {Pressable, StyleSheet, Text, View} from 'react-native';
import {Banner, BrandHeader, Button, Hero, LinkButton, Screen, Steps} from '../components/ui';
import {colors, radius, shadow, space} from '../components/theme';
import type {SimInfo} from '../native/SmsGateway';
import type {ScreenProps} from '../navigation/types';
import {gatewayEvents} from '../services/events';
import {log} from '../services/logger';
import {listSims, simLabel, toSelected} from '../services/simService';
import {prefs} from '../storage/prefs';
import {useGatewayStore} from '../store/gatewayStore';

export default function SimSelectionScreen({navigation, route}: ScreenProps<'SimSelection'>) {
  const fromSettings = !!route.params?.fromSettings;
  const [sims, setSims] = useState<SimInfo[]>([]);
  const [state, setState] = useState<'loading' | 'ok' | 'PERMISSION_REQUIRED' | 'ERROR'>('loading');
  const [chosen, setChosen] = useState<number | null>(null);
  const [previous, setPrevious] = useState<number | null>(null);

  const load = useCallback(async () => {
    setState('loading');
    const [res, selected] = await Promise.all([listSims(), prefs.getSelectedSim()]);
    setSims(res.sims);
    setState(res.state);
    setPrevious(selected?.subscriptionId ?? null);
    const stillThere = res.sims.find(x => x.subscriptionId === selected?.subscriptionId);
    setChosen(stillThere ? stillThere.subscriptionId : res.sims.length === 1 ? res.sims[0].subscriptionId : null);
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  async function onSave() {
    const sim = sims.find(x => x.subscriptionId === chosen);
    if (!sim) {
      return;
    }
    await prefs.setSelectedSim(toSelected(sim));
    await prefs.patchRuntime({lastSimReportedSubId: null, pauseReason: null});
    await log.info('SIM selected', simLabel(sim));
    gatewayEvents.emitChanged();
    await useGatewayStore.getState().refresh();
    if (fromSettings && navigation.canGoBack()) {
      navigation.goBack();
    } else {
      navigation.reset({index: 0, routes: [{name: 'Dashboard'}]});
    }
  }

  const selectedMissing = previous !== null && state === 'ok' && !sims.some(x => x.subscriptionId === previous);

  return (
    <Screen topInset>
      <BrandHeader />
      {!fromSettings ? <Steps current={3} /> : null}
      <Hero
        eyebrow="LAST STEP"
        title="Choose the SIM"
        subtitle="Pick the number parents and students know. Every portal SMS will go out from it."
      />
      {state === 'PERMISSION_REQUIRED' ? (
        <Banner
          tone="danger"
          title="Permission needed"
          message="Allow the app to see SIM cards first."
          actionLabel="Allow"
          onAction={() => navigation.navigate('Permissions')}
        />
      ) : null}
      {state === 'ok' && sims.length === 0 ? (
        <Banner tone="danger" title="No SIM found" message="Insert a SIM with SMS service (or turn it on in Android settings), then tap Refresh." />
      ) : null}
      {selectedMissing ? (
        <Banner tone="warning" title="Your SIM is no longer available" message="It was removed, turned off or swapped. Choose a SIM to start sending again." />
      ) : null}

      {sims.map(sim => {
        const active = sim.subscriptionId === chosen;
        return (
          <Pressable
            key={sim.subscriptionId}
            onPress={() => setChosen(sim.subscriptionId)}
            accessibilityRole="radio"
            accessibilityState={{selected: active}}
            style={[s.sim, active && s.simOn]}>
            <View style={[s.slot, active && s.slotOn]}>
              <Text style={[s.slotText, active && s.slotTextOn]}>{sim.slotIndex >= 0 ? sim.slotIndex + 1 : '•'}</Text>
            </View>
            <View style={s.flex}>
              <Text style={s.carrier}>{sim.carrierName || sim.displayName || 'Unknown carrier'}</Text>
              <Text style={s.meta}>
                {sim.slotIndex >= 0 ? `SIM ${sim.slotIndex + 1}` : 'SIM'}
                {sim.phoneNumber ? ` · ${sim.phoneNumber}` : ''}
                {sim.isEmbedded ? ' · eSIM' : ''}
              </Text>
            </View>
            <View style={[s.radio, active && s.radioOn]}>{active ? <View style={s.radioInner} /> : null}</View>
          </Pressable>
        );
      })}

      <Button title="Use this SIM" onPress={onSave} disabled={chosen === null} large style={s.mt} />
      <LinkButton title={state === 'loading' ? 'Looking for SIM cards…' : 'Refresh'} onPress={load} />
    </Screen>
  );
}

const s = StyleSheet.create({
  flex: {flex: 1},
  mt: {marginTop: space.md},
  sim: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1.5,
    borderColor: colors.border,
    padding: space.lg,
    marginBottom: space.md,
    ...shadow,
  },
  simOn: {borderColor: colors.primary, backgroundColor: colors.primary50},
  slot: {width: 44, height: 44, borderRadius: radius.md, backgroundColor: colors.neutralBg, alignItems: 'center', justifyContent: 'center'},
  slotOn: {backgroundColor: colors.primary},
  slotText: {fontSize: 18, fontWeight: '900', color: colors.muted},
  slotTextOn: {color: '#fff'},
  carrier: {fontSize: 17, fontWeight: '800', color: colors.text},
  meta: {fontSize: 13, color: colors.muted, marginTop: 2},
  radio: {width: 24, height: 24, borderRadius: 12, borderWidth: 2, borderColor: colors.borderStrong, alignItems: 'center', justifyContent: 'center'},
  radioOn: {borderColor: colors.primary},
  radioInner: {width: 12, height: 12, borderRadius: 6, backgroundColor: colors.primary},
});
