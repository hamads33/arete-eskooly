import React, {useEffect, useState} from 'react';
import {Card, InfoRow, Paragraph, Screen, SectionTitle} from '../components/ui';
import SmsGateway, {type DeviceSnapshot} from '../native/SmsGateway';
import {getOrCreateDeviceUuid} from '../storage/secureStore';
import {useGatewayStore} from '../store/gatewayStore';
import {apiUrlFor} from '../utils/url';

export default function AboutScreen() {
  const pairing = useGatewayStore(s => s.pairing);
  const [snap, setSnap] = useState<DeviceSnapshot | null>(null);
  const [uuid, setUuid] = useState<string>('');

  useEffect(() => {
    SmsGateway.getDeviceSnapshot().then(setSnap).catch(() => null);
    getOrCreateDeviceUuid().then(setUuid).catch(() => null);
  }, []);

  return (
    <Screen>
      <Card>
        <InfoRow label="App" value="Arete-SMS" />
        <InfoRow label="Version" value={snap ? `${snap.appVersion} (${snap.appBuild})${snap.debugBuild ? ' debug' : ''}` : '—'} />
        <InfoRow label="API" value={pairing ? apiUrlFor(pairing.baseUrl) : 'Not paired'} />
        <InfoRow label="Device ID" value={uuid ? `${uuid.slice(0, 8)}…${uuid.slice(-4)}` : '—'} />
        <InfoRow label="Phone" value={snap ? `${snap.manufacturer} ${snap.model}` : '—'} />
        <InfoRow label="Android" value={snap ? `${snap.androidVersion} (API ${snap.sdkInt})` : '—'} />
        <InfoRow label="Battery" value={snap && snap.batteryLevel >= 0 ? `${snap.batteryLevel}%${snap.charging ? ' · charging' : ''}` : '—'} />
        <InfoRow label="Network" value={snap?.networkType ?? '—'} />
      </Card>
      <SectionTitle>Privacy</SectionTitle>
      <Card>
        <Paragraph>
          This app only sends SMS that your LMS queued. Phone numbers and message text are exchanged only with the LMS
          server you paired with. There is no advertising, analytics, tracking, cloud database or external logging.
        </Paragraph>
        <Paragraph muted>
          It does not read your SMS inbox or contacts, and it does not collect IMEI or hardware serial numbers. The
          device token is encrypted with the Android Keystore and is never displayed. Logs mask phone numbers and do not
          store message text.
        </Paragraph>
      </Card>
    </Screen>
  );
}
